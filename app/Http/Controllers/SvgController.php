<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

class SvgController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'svg_files.*' => 'required|file|mimes:svg',
        ]);

        $files = $request->file('svg_files');
        $results = [];
        $outputDir = storage_path('app/svg_output'); 
        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        foreach ($files as $file) {
            $svgContent = file_get_contents($file->getPathname());

            if (empty($svgContent)) {
                $results[] = ['error' => 'SVG file is empty or invalid'];
                continue;
            }
            $svgContent = $this->removeDefs($svgContent);
            $svgContent = $this->updateTransform($svgContent);

            $boundingBox = $this->calculateBoundingBox($svgContent);
            if (!$boundingBox) {
                $results[] = ['error' => 'Unable to calculate bounding box'];
                continue;
            }
            $updatedSvgContent = $this->updateSvg($svgContent, $boundingBox);

            $newFileName = $file->getClientOriginalName();
            $newFilePath = $outputDir . '/' . $newFileName;
            File::put($newFilePath, $updatedSvgContent);

            $results[] = [
                'original' => $file->getClientOriginalName(),
                'new_file' => $newFileName,
                'viewBox' => "{$boundingBox['minX']} {$boundingBox['minY']} {$boundingBox['width']} {$boundingBox['height']}",
                'width' => $boundingBox['width'],
                'height' => $boundingBox['height'],
            ];
        }

        return response()->json([
            'message' => 'SVG files have been processed and saved.',
            'results' => $results,
        ]);
    }
    private function removeDefs($svgContent)
    {
        $pattern = '/<defs>.*?<\/defs>/is';
        return preg_replace($pattern, '', $svgContent);
    }

    private function updateTransform($svgContent)
    {
        $pattern = '/transform="translate\([^\)]+\)"/';
        return preg_replace($pattern, 'transform="translate(0 0)"', $svgContent);
    }
    private function calculateBoundingBox($svgContent)
    {
        $xml = simplexml_load_string($svgContent);
        $namespaces = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('svg', $namespaces['']);
    
        $minX = PHP_FLOAT_MAX;
        $minY = PHP_FLOAT_MAX;
        $maxX = PHP_FLOAT_MIN;
        $maxY = PHP_FLOAT_MIN;
    
        $paths = $xml->xpath('//svg:path');
        foreach ($paths as $path) {
            $d = (string) $path['d'];
            preg_match_all('/[MLC]?\s*(-?\d+\.?\d*)\s*,?\s*(-?\d+\.?\d*)/', $d, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $index => $x) {
                    $y = $matches[2][$index];
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }
    
        if ($minX === PHP_FLOAT_MAX || $minY === PHP_FLOAT_MAX || $maxX === PHP_FLOAT_MIN || $maxY === PHP_FLOAT_MIN) {
            return false;
        }
    
        return [
            'minX' => $minX,
            'minY' => $minY,
            'width' => $maxX - $minX,
            'height' => $maxY - $minY,
        ];
    }
    private function updateSvg($svgContent, $boundingBox)
    {
        $xml = simplexml_load_string($svgContent);
        $xml->registerXPathNamespace('svg', $xml->getNamespaces(true)['']);

        $xml['viewBox'] = "{$boundingBox['minX']} {$boundingBox['minY']} {$boundingBox['width']} {$boundingBox['height']}";
        $xml['width'] = $boundingBox['width'];
        $xml['height'] = $boundingBox['height'];

        return $xml->asXML();
    }
}

