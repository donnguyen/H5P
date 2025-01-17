<?php

namespace EscolaLms\HeadlessH5P\Http\Controllers;

//use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use EscolaLms\HeadlessH5P\Http\Controllers\Swagger\LibraryApiSwagger;
use EscolaLms\HeadlessH5P\Services\HeadlessH5PService;
use EscolaLms\HeadlessH5P\Services\Contracts\HeadlessH5PServiceContract;

use EscolaLms\HeadlessH5P\Http\Requests\LibraryStoreRequest;
use Illuminate\Routing\Controller;

class LibraryApiController extends Controller implements LibraryApiSwagger
{
    private HeadlessH5PServiceContract $hh5pService;

    public function __construct(HeadlessH5PServiceContract $hh5pService)
    {
        $this->hh5pService = $hh5pService;
    }

    public function index(Request $request): JsonResponse
    {
        $libraries = $this->hh5pService->listLibraries();

        return response()->json($libraries, 200);
    }

    public function store(LibraryStoreRequest $request): JsonResponse
    {
        $valid = $this->hh5pService->validatePackage($request->file('h5p_file'));
        if ($valid) {
            $this->hh5pService->savePackage();
        }

        return response()->json([
            'valid' => $valid,
            'messages' => $this->hh5pService->getMessages('updated'),
            'errors' => $this->hh5pService->getMessages('error'),
        ], $valid ? 200 : 422);
    }

    public function libraries(Request $request): JsonResponse
    {
        $libraries = $this->hh5pService->getLibraries(
            $request->get('machineName'),
            $request->get('majorVersion'),
            $request->get('minorVersion')
        );

        return response()->json($libraries, 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $valid = $this->hh5pService->deleteLibrary($id);
        
        return response()->json([
            'valid' => $valid,
            'messages' =>  $valid ? "Library $id deleted" : "",
            'errors' => !$valid ? "Library $id note deleted" : "",
        ], $valid ? 200 : 422);
    }
}
