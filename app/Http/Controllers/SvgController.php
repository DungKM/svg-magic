<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SvgController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'svg_file' => 'required|file|mimes:svg',
        ]);
    
        $file = $request->file('svg_file');
        $svgContent = file_get_contents($file->getPathname());
    
        if (empty($svgContent)) {
            return response()->json(['error' => 'SVG file is empty or invalid'], 400);
        }
    
        $xml = simplexml_load_string($svgContent);
        $namespaces = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('svg', $namespaces['']);
    
        $paths = $xml->xpath('//svg:path');
        if (empty($paths)) {
            return response()->json(['error' => 'No <path> elements found in the SVG'], 400);
        }
    
        $boundingBox = $this->calculateBoundingBox($svgContent);
        if (!$boundingBox) {
            return response()->json(['error' => 'Unable to calculate bounding box'], 400);
        }
    
        return response()->json([
            'viewBox' => "{$boundingBox['minX']} {$boundingBox['minY']} {$boundingBox['width']} {$boundingBox['height']}",
            'width' => $boundingBox['width'],
            'height' => $boundingBox['height'],
        ]);
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

        foreach ($xml->xpath('//svg:path') as $path) {
            $d = (string)$path['d'];
            preg_match_all('/-?\d+(\.\d+)?/', $d, $matches);
            $coordinates = array_map('floatval', $matches[0]);

            for ($i = 0; $i < count($coordinates); $i += 2) {
                $x = $coordinates[$i];
                $y = $coordinates[$i + 1];

                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        return [
            'minX' => $minX,
            'minY' => $minY,
            'maxX' => $maxX,
            'maxY' => $maxY,
            'width' => $maxX - $minX,
            'height' => $maxY - $minY,
        ];
    }
}
