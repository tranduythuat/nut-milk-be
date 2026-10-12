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
use App\Services\OrderCancellationService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Controllers\Concerns\AuthorizesOrderOwner;
use App\Http\Requests\CancelOrderRequest;
use RuntimeException;

class DeliveryController extends Controller
{
    use AuthorizesOrderOwner;

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
    ): JsonResponse {
        $newStatus = DeliveryStatus::from($request->validated('status'));
        $note = $request->validated('note');

        try {
            $delivery = match ($newStatus) {
                DeliveryStatus::SHIPPING => $workflowService->startShipping($delivery, $note),
                DeliveryStatus::DELIVERED => $workflowService->completeDelivery($delivery, $note),
                default => $deliveryStatusService->updateStatus($delivery, $newStatus, $note),
            };

            return response()->json([
                'success' => true,
                'data' => new DeliveryResource($delivery),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function cancel(
        CancelOrderRequest $request,
        Delivery $delivery,
        OrderCancellationService $service
    ): JsonResponse {
        $delivery->load('order');
        $this->authorizeOrderOwnerOrStaff($request, $delivery->order);

        try {
            $delivery = $service->cancelDelivery($delivery, $request->validated('note'));

            return response()->json([
                'success' => true,
                'message' => 'Đợt giao đã được hủy.',
                'data' => new DeliveryResource($delivery),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
