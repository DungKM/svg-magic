<?php

use App\Http\Controllers\SvgController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::post('/upload-svg', [SvgController::class, 'upload'])->name('svg.upload');

Route::get('/download-svg/{filename}', function ($filename) {
    $filePath = storage_path('app/svg_output/' . $filename);
    
    if (file_exists($filePath)) {
        return response()->download($filePath);
    }
    
    return response()->json(['error' => 'File not found'], 404);
});
