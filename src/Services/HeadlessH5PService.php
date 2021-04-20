<?php

namespace EscolaLms\HeadlessH5P\Services;

use H5PCore;
use H5peditor;
use H5PEditorEndpoints;
use H5PFrameworkInterface;
use H5PFileStorage;
use H5PStorage;
use H5PValidator;
use EditorStorage;
use EditorAjaxRepository;
use H5peditorStorage;
use H5PEditorAjaxInterface;
use H5PContentValidator;
use H5peditorFile;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use EscolaLms\HeadlessH5P\Services\Contracts\HeadlessH5PServiceContract;
use EscolaLms\HeadlessH5P\Repositories\H5PRepository;
//use EscolaLms\HeadlessH5P\Repositories\H5PFileStorageRepository;
use EscolaLms\HeadlessH5P\Repositories\H5PEditorAjaxRepository;
use EscolaLms\HeadlessH5P\Repositories\H5PEditorStorageRepository;
use EscolaLms\HeadlessH5P\Models\H5PLibrary;
use EscolaLms\HeadlessH5P\Models\H5PContent;
use EscolaLms\HeadlessH5P\Exceptions\H5PException;

class HeadlessH5PService implements HeadlessH5PServiceContract
{
    private H5PFrameworkInterface $repository;
    private H5PFileStorage $fileStorage;
    private H5PCore $core;
    private H5PValidator $validator;
    private H5PStorage $storage;
    private H5peditorStorage $editorStorage;
    private H5PEditorAjaxInterface $editorAjaxRepository;
    private H5PContentValidator $contentValidator;
    private array $config;
    
    public function __construct(
        H5PFrameworkInterface $repository,
        H5PFileStorage $fileStorage,
        H5PCore $core,
        H5PValidator $validator,
        H5PStorage $storage,
        H5peditorStorage $editorStorage,
        H5PEditorAjaxInterface $editorAjaxRepository,
        H5peditor $editor,
        H5PContentValidator $contentValidator
    ) {
        $this->repository = $repository;
        $this->fileStorage = $fileStorage;
        $this->core = $core;
        $this->validator = $validator;
        $this->storage = $storage;
        $this->editorStorage = $editorStorage;
        $this->editorAjaxRepository = $editorAjaxRepository;
        $this->editor = $editor;
        $this->contentValidator = $contentValidator;
    }
  
    public function getEditor():H5peditor
    {
        return $this->editor;
    }
  
    public function getRepository():H5PFrameworkInterface
    {
        return $this->repository;
    }
  
    public function getFileStorage():H5PFileStorage
    {
        return $this->fileStorage;
    }
      
    public function getCore():H5PCore
    {
        return $this->core;
    }
      
    public function getValidator():H5PValidator
    {
        return $this->validator;
    }
  
    public function getStorage():H5PStorage
    {
        return $this->storage;
    }

    public function getEditorStorage():H5peditorStorage
    {
        return $this->editorStorage;
    }
  
    public function getContentValidator():H5PContentValidator
    {
        return $this->contentValidator;
    }

    /** Copy file to `getUploadedH5pPath` and validates its contents */
    public function validatePackage(UploadedFile $file, $skipContent = true, $h5p_upgrade_only = false): bool
    {
        rename($file->getPathName(), $this->getRepository()->getUploadedH5pPath());
        try {
            $isValid = $this->getValidator()->isValidPackage($skipContent, $h5p_upgrade_only);
        } catch (Exception $err) {
            var_dump($err);
        }
        return $isValid;
    }

    /**
   * Saves a H5P file
   *
   * @param null $content
   * @param int $contentMainId
   *  The main id for the content we are saving. This is used if the framework
   *  we're integrating with uses content id's and version id's
   * @param bool $skipContent
   * @param array $options
   * @return bool TRUE if one or more libraries were updated
   * TRUE if one or more libraries were updated
   * FALSE otherwise
   */
    public function savePackage(object $content = null, int $contentMainId = null, bool $skipContent = true, array $options = []): bool
    { // this is crazy, it does save package from `getUploadedH5pPath` path
        try {
            $this->getStorage()->savePackage($content, $contentMainId, $skipContent, $options);
        } catch (Exception $err) {
            return false;
        }
        return true;
    }

    public function getMessages($type = 'error')
    {
        return $this->getRepository()->getMessages($type);
    }

    public function listLibraries():Collection
    {
        return H5PLibrary::all();
    }

    public function getConfig():array
    {
        if (!isset($this->config)) {
            $config = (array)config('hh5p');
            $config['url'] = asset($config['url']);
            $config['ajaxPath'] = route($config['ajaxPath']).'/';
            $config['libraryUrl'] =  route($config['libraryUrl']);
            $config['get_laravelh5p_url'] =  url($config['get_laravelh5p_url']);
            $config['get_h5peditor_url'] =  url($config['get_h5peditor_url']);
            $config['get_h5pcore_url'] =  url($config['get_h5pcore_url']);
            $config['getCopyrightSemantics'] = $this->getContentValidator()->getCopyrightSemantics();
            $config['getMetadataSemantics'] = $this->getContentValidator()->getMetadataSemantics();
            $config['filesPath'] = url("h5p/editor");// TODO: diffrernt name
            $this->config = $config;
        }
        return $this->config ;
    }

    /**
     * Calls editor ajax actions
     */
    public function getLibraries(string $machineName = null, string $major_version = null, string $minor_version = null)
    {
        $lang = config('hh5p.language');

        // TODO this shoule be from config
        $libraries_url = url('h5p/libraries');

        if ($machineName) {
            $defaultLang = $this->getEditor()->getLibraryLanguage($machineName, $major_version, $minor_version, $lang);
            return $this->getEditor()->getLibraryData($machineName, $major_version, $minor_version, $lang, '', $libraries_url, $defaultLang);
        } else {
            return $this->getEditor()->getLibraries();
        }
    }

    public function getEditorSettings($content = null):array
    {
        $config = $this->getConfig();

        $settings =  [
            'baseUrl'            => $config['domain'],
            'url'                => $config['url'],
            'postUserStatistics' => false,
            'ajax'               => [
                'setFinished'     => $config['ajaxSetFinished'],
                'contentUserData' => $config['ajaxContentUserData'],
            ],
            'saveFreq' => false,
            'siteUrl'  => $config['domain'],
            'l10n'     => [
                'H5P' => __('h5p::h5p')
            ],
            'hubIsEnabled' => false,
        ];

        $settings['loadedJs'] = [];
        $settings['loadedCss'] = [];

        $settings['core'] = [
            'styles'  => [],
            'scripts' => [],
        ];
        foreach (H5PCore::$styles as $style) {
            $settings['core']['styles'][] = $config['get_h5pcore_url'].'/'.$style;
        }
        foreach (H5PCore::$scripts as $script) {
            $settings['core']['scripts'][] = $config['get_h5pcore_url'].'/'.$script;
        }
        $settings['core']['scripts'][] = $config['get_h5peditor_url'].'/scripts/h5peditor-editor.js';
        $settings['core']['scripts'][] = $config['get_h5peditor_url'].'/scripts/h5peditor-init.js';
        $settings['core']['scripts'][] = $config['get_h5peditor_url'].'/language/en.js'; // TODO this lang should vary depending on config

        //$settings['core']['scripts'][] = $config['get_laravelh5p_url'].'/laravel-h5p.js';

        $settings['editor'] = [
            'filesPath' => isset($content) ? url("h5p/content/$content") : url("h5p/editor"),
            'fileIcon'  => [
                'path'   => $config['fileIcon'],
                'width'  => 50,
                'height' => 50,
            ],
            //'ajaxPath' => route('h5p.ajax').'/?_token=' . $token ,
            'ajaxPath' => $config['ajaxPath'],
            // for checkeditor,
            'libraryUrl'         => $config['libraryUrl'],
            'copyrightSemantics' => $config['getCopyrightSemantics'],
            'metadataSemantics'  => $config['getMetadataSemantics'],
            'assets'             => [],
            'deleteMessage'      => trans('laravel-h5p.content.destoryed'),
            'apiVersion'         => H5PCore::$coreApi,
        ];

        if ($content !== null) {
            $settings['contents']["cid-$content"] = $this->getSettingsForContent($content);
            $settings['editor']['nodeVersionId'] = $content;
            $settings['nonce'] =  $settings['contents']["cid-$content"]['nonce'];
        } else {
            $settings['nonce'] = bin2hex(random_bytes(4));
        }

        // load core assets
        $settings['editor']['assets']['css'] = $settings['core']['styles'];
        $settings['editor']['assets']['js'] = $settings['core']['scripts'];

        // add editor styles
        foreach (H5peditor::$styles as $style) {
            $settings['editor']['assets']['css'][] = $config['get_h5peditor_url']. ('/'.$style);
        }
        // Add editor JavaScript
        foreach (H5peditor::$scripts as $script) {
            // We do not want the creator of the iframe inside the iframe
            if ($script !== 'scripts/h5peditor-editor.js') {
                $settings['editor']['assets']['js'][] = $config['get_h5peditor_url'].('/'.$script);
            }
        }

        $language_script = '/language/'.$config['get_language'].'.js';
        $settings['editor']['assets']['js'][] = $config['get_h5peditor_url'].($language_script);

        if ($content) {
            //$settings = self::get_content_files($settings, $content);
        }
        
        return $settings;
    }
    

    public function deleteLibrary($id):bool
    {
        $library = H5pLibrary::findOrFail($id);

        // TODO: check if runnable
        // TODO: check is usable, against content. If yes should ne be deleted


        // Error if in use
        // TODO implement getLibraryUsage, once content is ready
        // $usage = $this->getRepository()->getLibraryUsage($library);
        /*
        if ($usage['content'] !== 0 || $usage['libraries'] !== 0) {
            return redirect()->route('h5p.library.index')
                ->with('error', trans('laravel-h5p.library.used_library_can_not_destoroied'));
        }
        */

        $this->getRepository()->deleteLibrary($library);

        return true;
    }

    public function getSettingsForContent($id)
    {
        $content = $this->getCore()->loadContent($id);
        $content['metadata']['title'] = $content['title'];
        
        $safe_parameters = $this->getCore()->filterParameters($content);
        $library = $content['library'];


        $uberName =  $library['name']." ".$library['majorVersion'].'.'.$library['minorVersion'];

        $settings = [
            'library'         => $uberName,
            'content'         => $content,
            'jsonContent'     => json_encode([
                'params' => json_decode($safe_parameters),
                'metadata' => $content['metadata']
            ]),
            'fullScreen'      => $content['library']['fullscreen'],
            //'exportUrl'       => config('laravel-h5p.h5p_export') ? route('h5p.export', [$content['id']]) : '',
            //'embedCode'       => '<iframe src="'.route('h5p.embed', ['id' => $content['id']]).'" width=":w" height=":h" frameborder="0" allowfullscreen="allowfullscreen"></iframe>',
            //'resizeCode'      => '<script src="'.self::get_h5pcore_url('/js/h5p-resizer.js').'" charset="UTF-8"></script>',
            //'url'             => route('h5p.embed', ['id' => $content['id']]),
            'title'           => $content['title'],
            //'displayOptions'  => self::$core->getDisplayOptionsForView($content['disable'], $author_id),
            'contentUserData' => [
                0 => [
                    'state' => '{}',
                ],
            ],
            'nonce' => $content['nonce']
        ];

        return $settings;

        // Detemine embed type
        $embed = H5PCore::determineEmbedType($content['embedType'], $content['library']['embedTypes']);
        // Make sure content isn't added twice
        $cid = 'cid-'.$content['id'];
        if (!isset($settings['contents'][$cid])) {
            $settings['contents'][$cid] = self::get_content_settings($content);
            $core = self::$core;
            // Get assets for this content
            $preloaded_dependencies = $core->loadContentDependencies($content['id'], 'preloaded');
            $files = $core->getDependenciesFiles($preloaded_dependencies);
            self::alter_assets($files, $preloaded_dependencies, $embed);
            if ($embed === 'div') {
                foreach ($files['scripts'] as $script) {
                    $url = $script->path.$script->version;
                    if (!in_array($url, $settings['loadedJs'])) {
                        $settings['loadedJs'][] = self::get_h5plibrary_url($url);
                    }
                }
                foreach ($files['styles'] as $style) {
                    $url = $style->path.$style->version;
                    if (!in_array($url, $settings['loadedCss'])) {
                        $settings['loadedCss'][] = self::get_h5plibrary_url($url);
                    }
                }
            } elseif ($embed === 'iframe') {
                $settings['contents'][$cid]['scripts'] = $core->getAssetsUrls($files['scripts']);
                $settings['contents'][$cid]['styles'] = $core->getAssetsUrls($files['styles']);
            }
        }

        if ($embed === 'div') {
            return [
               'settings' => $settings,
               'embed'    => '<div class="h5p-content" data-content-id="'.$content['id'].'"></div>',
           ];
        } else {
            return [
               'settings' => $settings,
               'embed'    => '<div class="h5p-iframe-wrapper"><iframe id="h5p-iframe-'.$content['id'].'" class="h5p-iframe" data-content-id="'.$content['id'].'" style="height:1px" src="about:blank" frameBorder="0" scrolling="no"></iframe></div>',
           ];
        }
    }

    public function uploadFile($contentId, $field, $token, $nonce = null)
    {
        // TODO: implmenet nonce
        if (!$this->isValidEditorToken($token)) {
            throw new H5PException(H5PException::LIBRARY_NOT_FOUND);
        }

        $file = new H5peditorFile($this->getRepository());
        if (!$file->isLoaded()) {
            throw new H5PException(H5PException::FILE_NOT_FOUND);
        }
    
        // Make sure file is valid and mark it for cleanup at a later time
        if ($file->validate()) {
            $file_id = $this->getFileStorage()->saveFile($file, $contentId);
            $this->getEditorStorage()->markFileForCleanup($file_id, $nonce); // TODO: IMPLEMENT THIS
        }

        $result = json_decode($file->getResult());
        $result-> path = $file->getType() . 's/' . $file->getName() . '#tmp';

        return $result;
    }

    private function isValidEditorToken(string $token = null):bool
    {
        $isValidToken = $this->getEditor()->ajaxInterface->validateEditorToken($token);
        return !!$isValidToken;
    }
}