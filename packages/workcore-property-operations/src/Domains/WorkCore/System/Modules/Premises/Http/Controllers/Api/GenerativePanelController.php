<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Services\GenerativePanelService;

class GenerativePanelController extends Controller
{
    public function index(GenerativePanelService $service)
    {
        return response()->json(['data' => $service->available()]);
    }

    public function show(Request $request, Property $property, string $panel, GenerativePanelService $service)
    {
        abort_unless(function_exists('company') && company() && (int) $property->company_id === (int) company()->id, 403);
        $definition = config("managedpremises_generative_panels.{$panel}");
        abort_unless($definition, 404);
        $permission = $definition['permission'] ?? null;
        if ($permission && method_exists($request->user(), 'permission')) {
            abort_unless($request->user()->permission($permission) !== 'none', 403);
        }
        return response()->json(['data' => $service->build($property, $panel)]);
    }
}
