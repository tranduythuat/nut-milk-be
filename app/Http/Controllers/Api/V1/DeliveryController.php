<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Services\DeliveryAvailabilityService;
use App\Services\DeliveryStatusService;
use App\Services\OrderDeliveryWorkflowService;
use App\Http\Requests\UpdateDeliveryStatusRequest;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryAvailabilityService $availabilityService
    ) {}

    public function availableDates(): JsonResponse
    {
        $dates = $this->availabilityService
            ->getAvailableDates()
            ->map(fn($date) => [
                'date' => $date->toDateString(),
                'label' => $date->translatedFormat(
                    'l, d/m/Y'
                ),
            ]);

        return response()->json([
            'success' => true,
            'data' => $dates,
        ]);
    }

    public function availability(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'date' => [
                'required',
                'date',
            ],
        ]);

        $date = Carbon::parse(
            $validated['date']
        );

        $slots = $this->availabilityService->getAvailableSlots($date);

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date->toDateString(),
                'slots' => $slots,
            ],
        ]);
    }

    public function updateStatus(
        UpdateDeliveryStatusRequest $request,
        Delivery $delivery,
        DeliveryStatusService $deliveryStatusService,
        OrderDeliveryWorkflowService $workflowService
    ) {
        $newStatus = DeliveryStatus::from(
            $request->validated('status')
        );

        $note = $request->validated('note');


        if ($newStatus === DeliveryStatus::SHIPPING) {
            $delivery = $workflowService->startShipping(
                $delivery,
                $note
            );
        } elseif ($newStatus === DeliveryStatus::DELIVERED) {
            $delivery = $workflowService->completeDelivery(
                $delivery,
                $note
            );
        } else {
            $delivery = $deliveryStatusService->updateStatus(
                $delivery,
                $newStatus,
                $note
            );
        }

        return response()->json([
            'success' => true,
            'data' => new DeliveryResource($delivery),
        ]);
    }
}
