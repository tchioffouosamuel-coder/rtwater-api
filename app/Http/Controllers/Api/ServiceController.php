<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $services = Service::query()
            ->with('category')
            ->available()
            ->when($request->category_id, fn($q, $v) => $q->where('category_id', $v))
            ->when($request->max_price, fn($q, $v) => $q->where('price', '<=', $v))
            ->when($request->search, fn($q, $v) => $q->where('name', 'like', "%$v%"))
            ->paginate(12);

        return response()->json([
            'data'       => ServiceResource::collection($services),
            'pagination' => [
                'current_page' => $services->currentPage(),
                'last_page'    => $services->lastPage(),
                'per_page'     => $services->perPage(),
                'total'        => $services->total(),
            ],
        ]);
    }

    public function show(Service $service): JsonResponse
    {
        $service->load('category');
        $service->loadCount('bookings');

        return response()->json([
            'data' => new ServiceResource($service),
        ]);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('image_url')) {
            $validated['image_url'] = $request->file('image_url')
                ->store('services', 'public');
        }

        $service = Service::create($validated);
        $service->load('category');

        return response()->json([
            'message' => 'Service créé avec succès',
            'data'    => new ServiceResource($service),
        ], 201);
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('image_url')) {
            if ($service->image_url) {
                Storage::disk('public')->delete($service->image_url);
            }
            $validated['image_url'] = $request->file('image_url')
                ->store('services', 'public');
        }

        $service->update($validated);
        $service->load('category');

        return response()->json([
            'message' => 'Service mis à jour avec succès',
            'data'    => new ServiceResource($service),
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        $bookingsPending = $service->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        if ($bookingsPending > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer : '
                    . $bookingsPending
                    . ' réservation(s) en cours',
            ], 422);
        }

        if ($service->image_url) {
            Storage::disk('public')->delete($service->image_url);
        }

        $service->delete();

        return response()->json([
            'message' => 'Service supprimé avec succès',
        ]);
    }
}
