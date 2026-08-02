<?php

namespace App\Http\Controllers\Api\V1\Titan;

use App\Domains\WorkCore\System\AI\Tools\AiToolRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ToolController extends Controller
{
    public function index(AiToolRegistry $registry): JsonResponse
    {
        $tools = array_map(static fn ($tool): array => [
            'name' => $tool->name,
            'description' => $tool->description,
            'kind' => $tool->kind,
            'target' => $tool->target,
            'input_schema' => $tool->inputSchema,
            'output_schema' => $tool->outputSchema,
            'capability' => $tool->capability,
            'permission' => $tool->permission,
            'risk' => $tool->risk,
            'requires_confirmation' => $tool->requiresConfirmation,
            'data_classification' => $tool->dataClassification,
            'channels' => $tool->channels,
            'enabled' => $tool->enabled,
        ], array_values($registry->all()));

        return response()->json(['data' => $tools, 'count' => count($tools)]);
    }
}
