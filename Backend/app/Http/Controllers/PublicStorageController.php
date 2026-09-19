<?php

namespace App\Http\Controllers;

use App\Support\PublicStorage;
use Illuminate\Http\Response;

class PublicStorageController extends Controller
{
    public function __invoke(string $path): Response
    {
        return PublicStorage::response($path);
    }
}
