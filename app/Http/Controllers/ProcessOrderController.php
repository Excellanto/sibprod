<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Client;
use App\Models\ClientModel;
use App\Models\OrderPO;
use App\Models\SiteSetting;
use App\Models\Leather;
use App\Models\Colour;
use App\Models\Lining;
use App\Models\DesignName;
use App\Models\DesignDescription;
use App\Models\Special;
use App\Models\EndWidth;
use App\Models\Buckle;
use App\Models\BuckleWidth;
use App\Models\StrapLengthLongPart;
use App\Models\StrapLengthShortPart;
use App\Models\LoopBand;
use App\Models\ThreadSize;
use App\Models\StampingShort;
use App\Models\StampingLong;
use App\Models\StrapPicture;
use App\Models\NoteInfoLine1;
use App\Models\NoteInfoLine2;
use App\Models\Tool;
use App\Models\QualityControl;
use App\Models\QcRegister;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class ProcessOrderController extends Controller
{
	public function index(Request $request): Response
	{
		try {
			// Fetch OrderPos Status values from site_settings
			$setting = SiteSetting::where('key', 'orderpos_status')->first();
			$orderPosStatuses = [];
			
			if ($setting && $setting->type === 'json') {
				$decoded = json_decode($setting->value, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
					$orderPosStatuses = $decoded;
				}
			}
			
			// If no data exists, initialize with default values
			if (empty($orderPosStatuses)) {
				$orderPosStatuses = [
					['id' => 1, 'name' => 'Newly Added'],
					['id' => 2, 'name' => 'In Production'],
					['id' => 3, 'name' => 'Produced'],
				];
			}

			// Fetch Orders Status values from site_settings - use exactly what's stored, no modifications
			$ordersStatusSetting = SiteSetting::where('key', 'orders_status')->first();
			$ordersStatuses = [];
			
			if ($ordersStatusSetting && $ordersStatusSetting->type === 'json') {
				$decoded = json_decode($ordersStatusSetting->value, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
					$ordersStatuses = $decoded;
				}
			}
			
			// If no data exists in site_settings, return empty array
			// Statuses should be managed through App Data Values interface, not hardcoded here

			// Create a mapping of status ID to name for orders
			$ordersStatusMap = [];
			foreach ($ordersStatuses as $status) {
				if (isset($status['id'])) {
					$ordersStatusMap[$status['id']] = $status['name'];
				}
			}
			
			// Create a mapping of status name to display name (for backward compatibility)
			// Also create mapping by ID if status is stored as ID
			$statusMap = [];
			foreach ($orderPosStatuses as $status) {
				$statusMap[$status['name']] = $status['name'];
				if (isset($status['id'])) {
					$statusMap[$status['id']] = $status['name'];
				}
			}

			// Build query with filters
			$query = Order::with(['client', 'orderPOs.qualityControls']);

			// Filter by Order Number
			if ($request->filled('order_number')) {
				$query->where('OrderNumber', 'like', '%' . $request->order_number . '%');
			}

			// Filter by Client ID or Client Name
			if ($request->filled('client_id')) {
				$query->where('ClientId', $request->client_id);
			} elseif ($request->filled('client_name') && strlen($request->client_name) >= 2) {
				$query->whereHas('client', function ($q) use ($request) {
					$q->where('client_name', 'like', '%' . $request->client_name . '%');
				});
			}

			// Filter by Status
			if ($request->filled('status')) {
				$query->where('Status', $request->status);
			}

			// Filter by Model Number (search in OrderPO.ClientModel)
			if ($request->filled('model_number') && strlen($request->model_number) >= 2) {
				$query->whereHas('orderPOs', function ($q) use ($request) {
					$q->where('ClientModel', 'like', '%' . $request->model_number . '%');
				});
			}

			// Filter by Ref Number (search in OrderPO.RefNumber)
			if ($request->filled('ref_number') && strlen($request->ref_number) >= 2) {
				$query->whereHas('orderPOs', function ($q) use ($request) {
					$q->where('RefNumber', 'like', '%' . $request->ref_number . '%');
				});
			}

			// Server-side sorting (whitelist to prevent SQL injection)
			$sortField = $request->input('sortField', 'dateCreated');
			$sortOrder = (int) $request->input('sortOrder', -1);
			$direction = $sortOrder === 1 ? 'asc' : 'desc';

			$fieldMap = [
				'orderId' => 'orders.OrderId',
				'orderNumber' => 'orders.OrderNumber',
				'totalQuantity' => 'orders.QtyTotal',
				'status' => 'orders.Status',
				'dateCreated' => 'orders.created_at',
				'deliveryDate' => 'orders.DeliveryDate',
			];

			if ($sortField === 'clientName') {
				$query->leftJoin('clients', 'orders.ClientId', '=', 'clients.id')
					->select('orders.*')
					->orderBy('clients.client_name', $direction);
			} elseif (isset($fieldMap[$sortField])) {
				$query->orderBy($fieldMap[$sortField], $direction);
			} else {
				$query->orderBy('orders.created_at', 'desc');
			}

			// Fetch orders from database with filters - use pagination
			$ordersPaginated = $query
				->paginate(15)
				->withQueryString();

			$qcStatusSetting = SiteSetting::where('key', 'quality_control_status')->first();
			$qualityControlStatusOptions = [];
			if ($qcStatusSetting && $qcStatusSetting->type === 'json') {
				$decoded = json_decode($qcStatusSetting->value, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
					$qualityControlStatusOptions = $decoded;
				}
			}
			if (empty($qualityControlStatusOptions)) {
				$qualityControlStatusOptions = [
					['id' => 1, 'name' => 'Pending'],
					['id' => 2, 'name' => 'In Progress'],
					['id' => 3, 'name' => 'Completed'],
				];
			}
			$qcStatusMap = [];
			foreach ($qualityControlStatusOptions as $opt) {
				$qcStatusMap[(int) ($opt['id'] ?? 0)] = $opt['name'] ?? '';
			}
			$statusConfigForQc = $this->getOrderPOStatusConfig();

			// Map database fields to UI format
			$orders = [];
			foreach ($ordersPaginated->items() as $order) {
				$clientName = '-';
				if ($order->client && isset($order->client->client_name)) {
					$clientName = $order->client->client_name;
				}
				
				// Map OrderPO records
				$orderPOs = [];
				$uniqueModels = [];
				$uniqueRefs = [];
				
				foreach ($order->orderPOs as $orderPO) {
					// Map status to proper display name from OrderPos Status settings
					$statusValue = $orderPO->Status ?: '-';
					$displayStatus = $statusMap[$statusValue] ?? $statusValue;
					
					// Track unique models and refs for counting
					if ($orderPO->ClientModel && $orderPO->ClientModel !== '-') {
						$uniqueModels[$orderPO->ClientModel] = true;
					}
					if ($orderPO->RefNumber && $orderPO->RefNumber !== '-') {
						$uniqueRefs[$orderPO->RefNumber] = true;
					}
					
					$qualityControlEntries = [];
					foreach ($orderPO->qualityControls ?? [] as $qc) {
						$rawQcStatus = $qc->QC_status ?: '-';
						$qcStatusDisplay = is_numeric($rawQcStatus) && isset($qcStatusMap[(int) $rawQcStatus])
							? $qcStatusMap[(int) $rawQcStatus]
							: $rawQcStatus;
						$qualityControlEntries[] = [
							'id' => $qc->id,
							'qty' => $qc->Qty ?: '-',
							'gold' => $qc->Gold !== null ? $qc->Gold : '-',
							'silver' => $qc->Silver !== null ? $qc->Silver : '-',
							'qcStatus' => $qcStatusDisplay,
							'qcStatusId' => is_numeric($rawQcStatus) ? (int) $rawQcStatus : null,
							'qcCreationDate' => $qc->QC_creation_date ? $qc->QC_creation_date->format('Y-m-d') : null,
							'qcCompletionDate' => $qc->QC_completion_date ? $qc->QC_completion_date->format('Y-m-d') : null,
						];
					}

					$orderPOs[] = [
						'id' => $orderPO->id,
						'orderId' => $order->OrderId, // Add parent OrderId for Edit/Delete operations
						'psNumber' => $orderPO->PsNumber ?: '-',
						'clientModel' => $orderPO->ClientModel ?: '-',
						'refNumber' => $orderPO->RefNumber ?: '-',
						'qty' => $orderPO->QtyByOrder ?: 0,
						'rate' => $orderPO->Rate !== null ? (float) $orderPO->Rate : null,
						'gold' => $orderPO->Gold ?: '-',
						'silver' => $orderPO->Silver ?: '-',
						'dateInProduction' => $orderPO->DateInProduction ? $orderPO->DateInProduction->format('Y-m-d') : null,
						'dateOfDelivery' => $orderPO->DateOfDelivery ? $orderPO->DateOfDelivery->format('Y-m-d') : null,
						'noOfPrintOut' => $orderPO->NoOfPrintOut ?: 0,
						'status' => $displayStatus,
						'statusRaw' => $statusValue, // Keep original for reference
						'qualityControlEntries' => $qualityControlEntries,
					];
				}
				
				// Map order status ID to name
				$orderStatusId = $order->Status;
				$orderStatusName = null;
				if (is_numeric($orderStatusId)) {
					$orderStatusId = (int) $orderStatusId; // Ensure it's an integer
					$orderStatusName = $ordersStatusMap[$orderStatusId] ?? $orderStatusId;
				} else {
					$orderStatusName = $orderStatusId ?: 'Pending';
					$orderStatusId = null; // Set to null if not numeric
				}

				$orders[] = [
					'orderId' => $order->OrderId,
					'orderNumber' => $order->OrderNumber ?: '-',
					'clientName' => $clientName,
					'clientId' => $order->ClientId, // Add clientId for fetching models
					'totalQuantity' => $order->QtyTotal ?: 0,
					'totalModels' => count($uniqueModels), // Count distinct models
					'totalRef' => count($uniqueRefs), // Count distinct refs
					'status' => $orderStatusId, // Keep ID as integer for dropdown value
					'statusName' => $orderStatusName, // Display name
					'dateCreated' => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : null,
					'deliveryDate' => $order->DeliveryDate ? $order->DeliveryDate->format('Y-m-d') : null,
					'orderPOs' => $orderPOs,
				];
			}

			return Inertia::render('Admin/OrderManagement/ProcessOrder', [
				'orders' => [
					'data' => $orders,
					'links' => $ordersPaginated->linkCollection()->toArray(),
					'meta' => [
						'current_page' => $ordersPaginated->currentPage(),
						'last_page' => $ordersPaginated->lastPage(),
						'per_page' => $ordersPaginated->perPage(),
						'total' => $ordersPaginated->total(),
						'from' => $ordersPaginated->firstItem(),
						'to' => $ordersPaginated->lastItem(),
					]
				],
				'orderPosStatuses' => $orderPosStatuses, // Pass statuses to frontend for reference
				'ordersStatuses' => $ordersStatuses, // Pass Orders Status values to frontend
				'completedQcStatusId' => $statusConfigForQc['completedQcStatusId'],
				'producedStatusId' => $statusConfigForQc['producedId'],
			]);
		} catch (\Exception $e) {
			// Log error and return empty array
			Log::error('ProcessOrderController::index - Error: ' . $e->getMessage(), [
				'exception' => $e,
				'trace' => $e->getTraceAsString()
			]);
			
			return Inertia::render('Admin/OrderManagement/ProcessOrder', [
				'orders' => [],
				'completedQcStatusId' => 3,
				'producedStatusId' => null,
			]);
		}
	}

	/**
	 * Display all Quality Control entries
	 */
	public function qualityControlIndex(Request $request): Response
	{
		try {
			$qcStatusSetting = SiteSetting::where('key', 'quality_control_status')->first();
			$qualityControlStatusOptions = [];
			if ($qcStatusSetting && $qcStatusSetting->type === 'json') {
				$decoded = json_decode($qcStatusSetting->value, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
					$qualityControlStatusOptions = $decoded;
				}
			}
			if (empty($qualityControlStatusOptions)) {
				$qualityControlStatusOptions = [
					['id' => 1, 'name' => 'Pending'],
					['id' => 2, 'name' => 'In Progress'],
					['id' => 3, 'name' => 'Completed'],
				];
			}
			$qcStatusMap = [];
			foreach ($qualityControlStatusOptions as $opt) {
				$qcStatusMap[(int) ($opt['id'] ?? 0)] = $opt['name'] ?? '';
			}

			$qcPaginated = QualityControl::with(['orderPO.order.client', 'orderPO.qualityControls', 'qcRegisters'])
				->orderBy('id', 'desc')
				->paginate(20);

			$qcEntries = [];
			foreach ($qcPaginated->items() as $qc) {
				$orderPO = $qc->orderPO;
				$order = $orderPO?->order;
				$orderNumber = $order ? ($order->OrderNumber ?: '-') : '-';
				$clientName = '-';
				if ($order && $order->client) {
					$clientName = $order->client->client_name ?: '-';
				}
				$rawQcStatus = $qc->QC_status ?: '-';
				$qcStatusDisplay = is_numeric($rawQcStatus) && isset($qcStatusMap[(int) $rawQcStatus])
					? $qcStatusMap[(int) $rawQcStatus]
					: $rawQcStatus;

				$qualityControlEntries = [];
				foreach ($orderPO?->qualityControls ?? [] as $otherQc) {
					$otherRaw = $otherQc->QC_status ?: '-';
					$otherDisplay = is_numeric($otherRaw) && isset($qcStatusMap[(int) $otherRaw])
						? $qcStatusMap[(int) $otherRaw]
						: $otherRaw;
					$qualityControlEntries[] = [
						'id' => $otherQc->id,
						'qty' => $otherQc->Qty ?: '-',
						'gold' => $otherQc->Gold !== null ? $otherQc->Gold : '-',
						'silver' => $otherQc->Silver !== null ? $otherQc->Silver : '-',
						'qcStatus' => $otherDisplay,
						'qcStatusId' => is_numeric($otherRaw) ? (int) $otherRaw : null,
					];
				}

				$parentPO = $orderPO ? [
					'id' => $orderPO->id,
					'orderId' => $order?->id,
					'qty' => $orderPO->QtyByOrder ?: 0,
					'poGold' => (int) (is_numeric($orderPO->Gold) ? $orderPO->Gold : 0),
					'poSilver' => (int) (is_numeric($orderPO->Silver) ? $orderPO->Silver : 0),
					'qualityControlEntries' => $qualityControlEntries,
				] : null;

				$registerTotal = $qc->qcRegisters->sum('qty');
				$qcMaxQty = (int) ($qc->Qty ?: 0);
				$goldNum = $this->qcMetalQtyToInt($qc->Gold);
				$silverNum = $this->qcMetalQtyToInt($qc->Silver);
				$hasBuckle = $goldNum > 0 || $silverNum > 0;
				$reasonMap = $this->getQcReasonMap();
				$qtyAccepted = $this->resolveQcQtyAccepted($qc->qcRegisters, $reasonMap, $qcMaxQty, (int) $registerTotal);
				$qcEntries[] = [
					'id' => $qc->id,
					'orderPOId' => $orderPO?->id,
					'orderId' => $order?->id,
					'orderNumber' => $orderNumber,
					'clientName' => $clientName,
					'psNumber' => $orderPO?->PsNumber ?: '-',
					'clientModel' => $orderPO?->ClientModel ?: '-',
					'refNumber' => $orderPO?->RefNumber ?: '-',
					'qty' => $qc->Qty ?: '-',
					'qcMaxQty' => $qcMaxQty,
					'gold' => $goldNum,
					'silver' => $silverNum,
					'qtyAccepted' => $qtyAccepted,
					'hasBuckle' => $hasBuckle,
					'qcStatus' => $qcStatusDisplay,
					'qcStatusId' => is_numeric($rawQcStatus) ? (int) $rawQcStatus : null,
					'qcCreationDate' => $qc->QC_creation_date ? $qc->QC_creation_date->format('Y-m-d') : null,
					'qcCompletionDate' => $qc->QC_completion_date ? $qc->QC_completion_date->format('Y-m-d') : null,
					'parentPO' => $parentPO,
					'qc_register' => $qc->qcRegisters->map(fn ($r) => [
						'id' => $r->id,
						'qty' => $r->qty,
						'qc_reason_id' => $r->qc_reason_id,
						'reason_name' => $reasonMap[$r->qc_reason_id] ?? '',
					])->values()->all(),
					'registerTotal' => $registerTotal,
					'canComplete' => $registerTotal === $qcMaxQty && $qcMaxQty > 0,
				];
			}

			$statusConfig = $this->getOrderPOStatusConfig();
			$inProgressQcStatusId = $this->getQCStatusIdByIndex(1) ?? 2;
			$qcReasons = $this->getQcReasonsList();

			return Inertia::render('Admin/OrderManagement/QualityControl', [
				'qcReasons' => $qcReasons,
				'qualityControlEntries' => [
					'data' => $qcEntries,
					'links' => $qcPaginated->linkCollection()->toArray(),
					'meta' => [
						'current_page' => $qcPaginated->currentPage(),
						'last_page' => $qcPaginated->lastPage(),
						'per_page' => $qcPaginated->perPage(),
						'total' => $qcPaginated->total(),
						'from' => $qcPaginated->firstItem(),
						'to' => $qcPaginated->lastItem(),
					],
				],
				'qualityControlStatusOptions' => $qualityControlStatusOptions,
				'completedQcStatusId' => $statusConfig['completedQcStatusId'],
				'inProgressQcStatusId' => $inProgressQcStatusId,
			]);
		} catch (\Exception $e) {
			Log::error('ProcessOrderController::qualityControlIndex - Error: ' . $e->getMessage(), [
				'exception' => $e,
				'trace' => $e->getTraceAsString(),
			]);
			return Inertia::render('Admin/OrderManagement/QualityControl', [
				'qcReasons' => [],
				'qualityControlEntries' => ['data' => [], 'links' => [], 'meta' => []],
				'qualityControlStatusOptions' => [],
				'completedQcStatusId' => 3,
				'inProgressQcStatusId' => 2,
			]);
		}
	}

	/**
	 * Start QC completion: set status to In Progress and return qc_register entries.
	 * Called when user clicks "Complete QC" on a Pending entry.
	 */
	public function startQCComplete($id)
	{
		$qc = QualityControl::with('qcRegisters')->findOrFail($id);
		$inProgressId = $this->getQCStatusIdByIndex(1);
		if ($inProgressId === null) {
			$inProgressId = 2;
		}

		$qc->update(['QC_status' => (string) $inProgressId]);

		$reasonMap = $this->getQcReasonMap();
		$registerEntries = $qc->qcRegisters->map(fn ($r) => [
			'id' => $r->id,
			'qty' => $r->qty,
			'qc_reason_id' => $r->qc_reason_id,
			'reason_name' => $reasonMap[$r->qc_reason_id] ?? '',
		]);

		return response()->json([
			'success' => true,
			'qc_register' => $registerEntries->values()->all(),
		]);
	}

	/**
	 * Save QC draft: store qc_register entries. Sum of qty must not exceed QC entry Qty.
	 */
	public function saveQCDraft(Request $request, $id)
	{
		$qc = QualityControl::findOrFail($id);
		$maxQty = (int) ($qc->Qty ?: 0);
		if ($maxQty <= 0) {
			return response()->json(['success' => false, 'message' => 'Invalid QC quantity.'], 422);
		}

		$validated = $request->validate([
			'items' => ['required', 'array'],
			'items.*.qty' => ['required', 'integer', 'min:1'],
			'items.*.qc_reason_id' => ['required', 'integer'],
		]);

		$total = 0;
		foreach ($validated['items'] as $item) {
			$total += (int) $item['qty'];
		}
		if ($total > $maxQty) {
			return response()->json([
				'success' => false,
				'message' => "Total quantity ({$total}) cannot exceed QC entry quantity ({$maxQty}).",
			], 422);
		}

		QcRegister::where('quality_control_id', $id)->delete();

		foreach ($validated['items'] as $item) {
			QcRegister::create([
				'quality_control_id' => $id,
				'qty' => (int) $item['qty'],
				'qc_reason_id' => (int) $item['qc_reason_id'],
			]);
		}

		// If total < maxQty and entry was Completed, revert to In Progress
		$completedQcStatusId = $this->getOrderPOStatusConfig()['completedQcStatusId'];
		$inProgressId = $this->getQCStatusIdByIndex(1) ?? 2;
		$wasCompleted = (string) $qc->QC_status === (string) $completedQcStatusId;
		if ($total < $maxQty && $wasCompleted) {
			$qc->update([
				'QC_status' => (string) $inProgressId,
				'QC_completion_date' => null,
			]);
		}

		return response()->json([
			'success' => true,
			'message' => 'Draft saved.',
			'total_qty' => $total,
			'can_complete' => $total === $maxQty,
		]);
	}

	/**
	 * Complete a Quality Control entry (set status to Completed, set completion date).
	 * Only allowed when sum of qc_register qty equals QC entry Qty.
	 */
	public function completeQC($id)
	{
		$qc = QualityControl::with('qcRegisters')->findOrFail($id);
		$statusConfig = $this->getOrderPOStatusConfig();
		$completedQcStatusId = $statusConfig['completedQcStatusId'];
		$maxQty = (int) ($qc->Qty ?: 0);

		$totalRegistered = $qc->qcRegisters->sum('qty');
		if ($totalRegistered !== $maxQty) {
			return response()->json([
				'success' => false,
				'message' => "Cannot complete: total registered quantity ({$totalRegistered}) must equal QC quantity ({$maxQty}).",
			], 422);
		}

		$qc->update([
			'QC_status' => (string) $completedQcStatusId,
			'QC_completion_date' => now()->format('Y-m-d'),
		]);

		return response()->json([
			'success' => true,
			'message' => 'QC entry completed successfully.',
		]);
	}

	/**
	 * Bulk accept QC: for each selected entry, register full qty as Accepted and mark Completed.
	 */
	public function bulkAcceptQC(Request $request)
	{
		$validated = $request->validate([
			'ids' => ['required', 'array', 'min:1'],
			'ids.*' => ['required', 'integer'],
		]);

		$acceptedReasonId = $this->ensureAcceptedQcReasonId();
		$statusConfig = $this->getOrderPOStatusConfig();
		$completedQcStatusId = $statusConfig['completedQcStatusId'];
		$inProgressId = $this->getQCStatusIdByIndex(1) ?? 2;

		$processed = 0;
		$skipped = 0;
		$errors = [];

		foreach ($validated['ids'] as $id) {
			$qc = QualityControl::with('qcRegisters')->find($id);
			if (! $qc) {
				$errors[] = "QC entry #{$id} not found.";
				continue;
			}

			if ($this->isQualityControlEntryCompleted($qc, $statusConfig)) {
				$skipped++;
				continue;
			}

			$maxQty = (int) ($qc->Qty ?: 0);
			if ($maxQty <= 0) {
				$errors[] = "QC entry #{$id} has invalid quantity.";
				continue;
			}

			try {
				$qc->update(['QC_status' => (string) $inProgressId]);

				QcRegister::where('quality_control_id', $id)->delete();
				QcRegister::create([
					'quality_control_id' => $id,
					'qty' => $maxQty,
					'qc_reason_id' => $acceptedReasonId,
				]);

				$qc->update([
					'QC_status' => (string) $completedQcStatusId,
					'QC_completion_date' => now()->format('Y-m-d'),
				]);

				$processed++;
			} catch (\Exception $e) {
				$errors[] = "QC entry #{$id}: {$e->getMessage()}";
			}
		}

		if ($processed === 0 && ! empty($errors)) {
			return response()->json([
				'success' => false,
				'processed' => 0,
				'skipped' => $skipped,
				'errors' => $errors,
				'message' => $errors[0],
			], 422);
		}

		$message = "{$processed} QC " . ($processed === 1 ? 'entry' : 'entries') . ' accepted successfully.';
		if ($skipped > 0) {
			$message .= " {$skipped} already completed " . ($skipped === 1 ? 'entry was' : 'entries were') . ' skipped.';
		}

		return response()->json([
			'success' => true,
			'processed' => $processed,
			'skipped' => $skipped,
			'errors' => $errors,
			'message' => $message,
		]);
	}

	/**
	 * Return a simple quantity distribution report grouped by QC reason.
	 * Report is available only for completed QC entries.
	 */
	public function qcDistributionReport($id)
	{
		$qc = QualityControl::with(['qcRegisters', 'orderPO.order'])->findOrFail($id);
		$statusConfig = $this->getOrderPOStatusConfig();

		if (! $this->isQualityControlEntryCompleted($qc, $statusConfig)) {
			return response()->json([
				'success' => false,
				'message' => 'QC report is available only for completed QC entries.',
			], 422);
		}

		$rows = $this->aggregateQcRegistersToRows($qc->qcRegisters);
		$totalRegistered = array_sum(array_map(fn ($row) => (int) ($row['qty'] ?? 0), $rows));
		$qcQty = (int) ($qc->Qty ?? 0);

		return response()->json([
			'success' => true,
			'report' => [
				'quality_control_id' => (int) $qc->id,
				'order_po_id' => (int) ($qc->orderspo_id ?? 0),
				'order_number' => (string) ($qc->orderPO?->order?->OrderNumber ?? '-'),
				'client_model' => (string) ($qc->orderPO?->ClientModel ?? '-'),
				'qc_qty' => $qcQty,
				'total_registered_qty' => $totalRegistered,
				'rows' => $rows,
			],
		]);
	}

	/**
	 * Aggregated QC quantity distribution for all completed QC entries on an Order PO.
	 */
	public function qcPoDistributionReport($orderPoId)
	{
		$orderPO = OrderPO::with(['order', 'qualityControls.qcRegisters'])->findOrFail($orderPoId);
		$statusConfig = $this->getOrderPOStatusConfig();

		$completedQcs = $orderPO->qualityControls->filter(fn ($qc) => $this->isQualityControlEntryCompleted($qc, $statusConfig));

		if ($completedQcs->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'PO QC report is available when at least one QC entry is completed.',
			], 422);
		}

		$allRegisters = $completedQcs->flatMap(fn ($qc) => $qc->qcRegisters);
		$combinedRows = $this->aggregateQcRegistersToRows($allRegisters);
		$combinedTotal = array_sum(array_map(fn ($row) => (int) ($row['qty'] ?? 0), $combinedRows));

		$qcEntries = [];
		foreach ($completedQcs as $qc) {
			$entryRows = $this->aggregateQcRegistersToRows($qc->qcRegisters);
			$entryTotal = array_sum(array_map(fn ($row) => (int) ($row['qty'] ?? 0), $entryRows));
			$qcEntries[] = [
				'quality_control_id' => (int) $qc->id,
				'qc_qty' => (int) ($qc->Qty ?? 0),
				'total_registered_qty' => $entryTotal,
				'rows' => $entryRows,
			];
		}

		usort($qcEntries, fn ($a, $b) => ($a['quality_control_id'] ?? 0) <=> ($b['quality_control_id'] ?? 0));

		return response()->json([
			'success' => true,
			'report' => [
				'order_po_id' => (int) $orderPO->id,
				'order_number' => (string) ($orderPO->order?->OrderNumber ?? '-'),
				'client_model' => (string) ($orderPO->ClientModel ?? '-'),
				'po_qty' => (int) ($orderPO->QtyByOrder ?? 0),
				'total_registered_qty' => $combinedTotal,
				'combined_rows' => $combinedRows,
				'qc_entries' => $qcEntries,
			],
		]);
	}

	/**
	 * Search QC reasons (min 2 characters). Returns JSON for autocomplete.
	 */
	public function searchQcReasons(Request $request)
	{
		$query = trim($request->get('q', ''));
		if (strlen($query) < 2) {
			return response()->json(['reasons' => []]);
		}

		$setting = SiteSetting::where('key', 'qc_reasons')->first();
		$reasons = [];
		if ($setting && $setting->type === 'json') {
			$decoded = json_decode($setting->value, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				$reasons = $decoded;
			}
		}

		$lower = mb_strtolower($query);
		$filtered = array_values(array_filter($reasons, function ($r) use ($lower) {
			$name = $r['name'] ?? '';
			return stripos($name, $lower) !== false;
		}));

		return response()->json(['reasons' => $filtered]);
	}

	/**
	 * Display Order POs with status "In Production" (2) only.
	 * Table includes all PO columns plus Order Number & Client Name.
	 */
	public function productionOrders(Request $request): Response
	{
		try {
			// Fetch OrderPos Status values for display
			$setting = SiteSetting::where('key', 'orderpos_status')->first();
			$orderPosStatuses = [];
			if ($setting && $setting->type === 'json') {
				$decoded = json_decode($setting->value, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
					$orderPosStatuses = $decoded;
				}
			}
			if (empty($orderPosStatuses)) {
				$orderPosStatuses = [
					['id' => 1, 'name' => 'Newly Added'],
					['id' => 2, 'name' => 'In Production'],
					['id' => 3, 'name' => 'Produced'],
				];
			}
			$statusMap = [];
			foreach ($orderPosStatuses as $status) {
				$statusMap[$status['name']] = $status['name'];
				if (isset($status['id'])) {
					$statusMap[$status['id']] = $status['name'];
				}
			}

			// Fetch Production Orders statuses including 4 (QC Completed)
			$statusConfig = $this->getOrderPOStatusConfig();
			$displayStatusIds = array_values(array_filter(
				$statusConfig['allowedIdsForProductionPage'],
				fn ($id) => $id !== null
			));
			$query = OrderPO::with(['order.client', 'qualityControls.qcRegisters'])
				->whereIn('Status', $displayStatusIds);

			$orderPOsPaginated = $query
				->orderBy('DateInProduction', 'desc')
				->orderBy('id', 'desc')
				->paginate(20);

			// Fetch Quality Control Status options (needed for resolving QC_status ID to name)
			$qcStatusSetting = SiteSetting::where('key', 'quality_control_status')->first();
			$qualityControlStatusOptions = [];
			if ($qcStatusSetting && $qcStatusSetting->type === 'json') {
				$decoded = json_decode($qcStatusSetting->value, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
					$qualityControlStatusOptions = $decoded;
				}
			}
			if (empty($qualityControlStatusOptions)) {
				$qualityControlStatusOptions = [
					['id' => 1, 'name' => 'Pending'],
					['id' => 2, 'name' => 'In Progress'],
					['id' => 3, 'name' => 'Completed'],
				];
			}
			$qcStatusMap = [];
			foreach ($qualityControlStatusOptions as $opt) {
				$qcStatusMap[(int) ($opt['id'] ?? 0)] = $opt['name'] ?? '';
			}

			$qcReasonMapForProduction = $this->getQcReasonMap();

			// Sync: POs with QC entries must have status 3 (QC Started)
			$inProductionId = $statusConfig['inProductionId'];
			$qcStartedId = $statusConfig['qcStartedId'];
			foreach ($orderPOsPaginated->items() as $orderPO) {
				if ($orderPO->qualityControls->isNotEmpty() && (int) $orderPO->Status === (int) $inProductionId) {
					$orderPO->Status = $qcStartedId;
					$orderPO->save();
				}
			}

			$productionOrders = [];
			foreach ($orderPOsPaginated->items() as $orderPO) {
				$order = $orderPO->order;
				$orderNumber = $order ? ($order->OrderNumber ?: '-') : '-';
				$clientName = '-';
				if ($order && $order->client) {
					$clientName = $order->client->client_name ?: '-';
				}
				$statusValue = $orderPO->Status ?: '-';
				$displayStatus = $statusMap[$statusValue] ?? $statusValue;

				// Map Quality Control entries for this PO (resolve QC_status ID to name for display)
				$qualityControlEntries = [];
				foreach ($orderPO->qualityControls as $qc) {
					$rawQcStatus = $qc->QC_status ?: '-';
					$qcStatusDisplay = is_numeric($rawQcStatus) && isset($qcStatusMap[(int) $rawQcStatus])
						? $qcStatusMap[(int) $rawQcStatus]
						: $rawQcStatus;
					$registerTotal = $qc->qcRegisters->sum('qty');
					$qcMaxQty = (int) ($qc->Qty ?: 0);
					$goldNum = $this->qcMetalQtyToInt($qc->Gold);
					$silverNum = $this->qcMetalQtyToInt($qc->Silver);
					$hasBuckle = $goldNum > 0 || $silverNum > 0;
					$qtyAccepted = $this->resolveQcQtyAccepted($qc->qcRegisters, $qcReasonMapForProduction, $qcMaxQty, (int) $registerTotal);
					$qualityControlEntries[] = [
						'id' => $qc->id,
						'qty' => $qc->Qty ?: '-',
						'qcMaxQty' => $qcMaxQty,
						'gold' => $goldNum,
						'silver' => $silverNum,
						'qtyAccepted' => $qtyAccepted,
						'hasBuckle' => $hasBuckle,
						'qcStatus' => $qcStatusDisplay,
						'qcStatusId' => is_numeric($rawQcStatus) ? (int) $rawQcStatus : null,
						'qcCreationDate' => $qc->QC_creation_date ? $qc->QC_creation_date->format('Y-m-d') : null,
						'qcCompletionDate' => $qc->QC_completion_date ? $qc->QC_completion_date->format('Y-m-d') : null,
					];
				}

				$productionOrders[] = [
					'id' => $orderPO->id,
					'orderId' => $orderPO->OrderId,
					'orderNumber' => $orderNumber,
					'clientName' => $clientName,
					'psNumber' => $orderPO->PsNumber ?: '-',
					'clientModel' => $orderPO->ClientModel ?: '-',
					'refNumber' => $orderPO->RefNumber ?: '-',
					'qty' => $orderPO->QtyByOrder ?: 0,
					'gold' => $orderPO->Gold ?: '-',
					'silver' => $orderPO->Silver ?: '-',
					'dateInProduction' => $orderPO->DateInProduction ? $orderPO->DateInProduction->format('Y-m-d') : null,
					'dateOfDelivery' => $orderPO->DateOfDelivery ? $orderPO->DateOfDelivery->format('Y-m-d') : null,
					'noOfPrintOut' => $orderPO->NoOfPrintOut ?: 0,
					'status' => $displayStatus,
					'statusRaw' => $statusValue,
					'qualityControlEntries' => $qualityControlEntries,
				'poGold' => (int) (is_numeric($orderPO->Gold) ? $orderPO->Gold : 0),
				'poSilver' => (int) (is_numeric($orderPO->Silver) ? $orderPO->Silver : 0),
				];
			}

			$statusConfig = $this->getOrderPOStatusConfig();

			return Inertia::render('Admin/OrderManagement/ProductionOrders', [
				'productionOrders' => [
					'data' => $productionOrders,
					'links' => $orderPOsPaginated->linkCollection()->toArray(),
					'meta' => [
						'current_page' => $orderPOsPaginated->currentPage(),
						'last_page' => $orderPOsPaginated->lastPage(),
						'per_page' => $orderPOsPaginated->perPage(),
						'total' => $orderPOsPaginated->total(),
						'from' => $orderPOsPaginated->firstItem(),
						'to' => $orderPOsPaginated->lastItem(),
					]
				],
				'orderPOStatusOptions' => $orderPosStatuses,
				'qualityControlStatusOptions' => $qualityControlStatusOptions,
				'productionOrderStatusIds' => $statusConfig['allowedIdsForProductionPage'],
				'producedStatusId' => $statusConfig['producedId'],
				'completedQcStatusId' => $statusConfig['completedQcStatusId'],
			]);
		} catch (\Exception $e) {
			Log::error('ProcessOrderController::productionOrders - Error: ' . $e->getMessage(), [
				'exception' => $e,
				'trace' => $e->getTraceAsString()
			]);
			return Inertia::render('Admin/OrderManagement/ProductionOrders', [
				'productionOrders' => ['data' => [], 'links' => [], 'meta' => []],
				'orderPOStatusOptions' => [],
				'qualityControlStatusOptions' => [],
				'productionOrderStatusIds' => [],
				'producedStatusId' => null,
				'completedQcStatusId' => null,
			]);
		}
	}

	/**
	 * Send Order PO for Quality Control - creates QC record
	 */
	public function sendForQC(Request $request, $id)
	{
		$orderPO = OrderPO::with('qualityControls')->findOrFail($id);
		$poQty = (int) ($orderPO->QtyByOrder ?? 0);
		$poGold = (int) (is_numeric($orderPO->Gold) ? $orderPO->Gold : 0);
		$poSilver = (int) (is_numeric($orderPO->Silver) ? $orderPO->Silver : 0);

		$existingQtySum = $orderPO->qualityControls->sum('Qty');
		$existingGoldSum = $orderPO->qualityControls->sum('Gold');
		$existingSilverSum = $orderPO->qualityControls->sum('Silver');
		$availableQty = max(0, $poQty - $existingQtySum);
		$availableGold = max(0, $poGold - $existingGoldSum);
		$availableSilver = max(0, $poSilver - $existingSilverSum);

		$rules = [
			'qty_type' => ['required', 'in:full,partial'],
			'gold' => ['nullable', 'integer', 'min:0'],
			'silver' => ['nullable', 'integer', 'min:0'],
		];
		if ($request->qty_type === 'partial') {
			$rules['qty'] = ['required', 'integer', 'min:1', 'max:' . max(1, $availableQty)];
		}

		$validated = $request->validate($rules);

		$qty = $validated['qty_type'] === 'full' ? $availableQty : (int) ($validated['qty'] ?? 0);
		if ($qty <= 0 || $qty > $availableQty) {
			return Redirect::back()->withErrors(['qty' => 'Qty cannot exceed available (' . $availableQty . '). PO total: ' . $poQty . ', already in QC: ' . $existingQtySum . '.']);
		}

		$gold = (int) ($validated['gold'] ?? 0);
		$silver = (int) ($validated['silver'] ?? 0);
		if (($gold + $silver) > $qty) {
			return Redirect::back()->withErrors(['gold' => 'Gold + Silver cannot exceed Qty.']);
		}
		if ($gold > $availableGold) {
			return Redirect::back()->withErrors(['gold' => 'Gold cannot exceed available (' . $availableGold . ').']);
		}
		if ($silver > $availableSilver) {
			return Redirect::back()->withErrors(['silver' => 'Silver cannot exceed available (' . $availableSilver . ').']);
		}

		$pendingQcStatusId = $this->getQCStatusIdByIndex(0);
		QualityControl::create([
			'orderspo_id' => $orderPO->id,
			'Qty' => $qty,
			'Gold' => $gold,
			'Silver' => $silver,
			'QC_status' => $pendingQcStatusId !== null ? (string) $pendingQcStatusId : 'Pending',
			'QC_creation_date' => now()->format('Y-m-d'),
		]);

		// Auto-update Order PO status to QC Started (ID from orderpos_status index 2) when first QC entry is created
		$statusConfig = $this->getOrderPOStatusConfig();
		$qcStartedId = $statusConfig['qcStartedId'];
		if ($qcStartedId !== null) {
			$orderPO->Status = $qcStartedId;
			$orderPO->save();
		}

		return Redirect::back()->with('success', 'Sent for QC successfully.');
	}

	/**
	 * Bulk send Order POs for QC using full available qty (balance if QC already exists).
	 * Gold and Silver are always set to 0.
	 */
	public function bulkSendForQC(Request $request)
	{
		$validated = $request->validate([
			'order_po_ids' => ['required', 'array', 'min:1'],
			'order_po_ids.*' => ['required', 'integer', 'exists:ordersPO,id'],
		]);

		$ids = array_values(array_unique(array_map('intval', $validated['order_po_ids'])));
		$orderPOs = OrderPO::with('qualityControls')->whereIn('id', $ids)->get()->keyBy('id');

		$pendingQcStatusId = $this->getQCStatusIdByIndex(0);
		$statusConfig = $this->getOrderPOStatusConfig();
		$qcStartedId = $statusConfig['qcStartedId'];
		$producedId = $statusConfig['producedId'] ?? 4;

		$sent = 0;
		$skipped = [];

		DB::transaction(function () use ($ids, $orderPOs, $pendingQcStatusId, $qcStartedId, $producedId, &$sent, &$skipped) {
			foreach ($ids as $id) {
				$orderPO = $orderPOs->get($id);
				if (! $orderPO) {
					$skipped[] = $id;
					continue;
				}

				$status = is_numeric($orderPO->Status) ? (int) $orderPO->Status : null;
				if ($status !== null && $status === (int) $producedId) {
					$skipped[] = $id;
					continue;
				}

				$poQty = (int) ($orderPO->QtyByOrder ?? 0);
				$existingQtySum = $orderPO->qualityControls->sum('Qty');
				$availableQty = max(0, $poQty - $existingQtySum);

				if ($availableQty < 1) {
					$skipped[] = $id;
					continue;
				}

				QualityControl::create([
					'orderspo_id' => $orderPO->id,
					'Qty' => $availableQty,
					'Gold' => 0,
					'Silver' => 0,
					'QC_status' => $pendingQcStatusId !== null ? (string) $pendingQcStatusId : 'Pending',
					'QC_creation_date' => now()->format('Y-m-d'),
				]);

				if ($qcStartedId !== null) {
					$orderPO->Status = $qcStartedId;
					$orderPO->save();
				}

				$sent++;
			}
		});

		if ($sent < 1) {
			return Redirect::back()->with('error', 'No POs were sent for QC. Selected rows had no available quantity.');
		}

		$message = "Sent {$sent} PO(s) for QC successfully.";
		if (count($skipped) > 0) {
			$message .= ' Skipped '.count($skipped).' with no available qty.';
		}

		return Redirect::back()->with('success', $message);
	}

	/**
	 * Update a Quality Control entry
	 */
	public function updateQC(Request $request, $id)
	{
		$qc = QualityControl::with('orderPO.qualityControls')->findOrFail($id);
		$orderPO = $qc->orderPO;
		$poQty = (int) ($orderPO->QtyByOrder ?? 0);
		$poGold = (int) (is_numeric($orderPO->Gold) ? $orderPO->Gold : 0);
		$poSilver = (int) (is_numeric($orderPO->Silver) ? $orderPO->Silver : 0);

		$existingQtySum = $orderPO->qualityControls->where('id', '!=', $qc->id)->sum('Qty');
		$existingGoldSum = $orderPO->qualityControls->where('id', '!=', $qc->id)->sum('Gold');
		$existingSilverSum = $orderPO->qualityControls->where('id', '!=', $qc->id)->sum('Silver');
		$availableQty = max(0, $poQty - $existingQtySum);
		$availableGold = max(0, $poGold - $existingGoldSum);
		$availableSilver = max(0, $poSilver - $existingSilverSum);

		$validated = $request->validate([
			'qty' => ['required', 'integer', 'min:1', 'max:' . max(1, $availableQty)],
			'gold' => ['nullable', 'integer', 'min:0', 'max:' . $availableGold],
			'silver' => ['nullable', 'integer', 'min:0', 'max:' . $availableSilver],
			'qc_status' => ['nullable'],
			'qc_creation_date' => ['nullable', 'date'],
			'qc_completion_date' => ['nullable', 'date'],
		]);

		$gold = (int) ($validated['gold'] ?? 0);
		$silver = (int) ($validated['silver'] ?? 0);
		$qty = (int) $validated['qty'];

		if (($gold + $silver) > $qty) {
			return Redirect::back()->withErrors(['gold' => 'Gold + Silver cannot exceed Qty.']);
		}

		$qcStatusValue = $validated['qc_status'] ?? $qc->QC_status;
		if ($qcStatusValue !== null && is_numeric($qcStatusValue)) {
			$qcStatusValue = (string) $qcStatusValue; // Store ID as string
		}

		$qc->update([
			'Qty' => $qty,
			'Gold' => $gold,
			'Silver' => $silver,
			'QC_status' => $qcStatusValue,
			'QC_creation_date' => $validated['qc_creation_date'] ?? $qc->QC_creation_date?->format('Y-m-d'),
			'QC_completion_date' => !empty($validated['qc_completion_date']) ? $validated['qc_completion_date'] : null,
		]);

		return Redirect::back()->with('success', 'QC entry updated successfully.');
	}

	/**
	 * Delete a Quality Control entry
	 */
	public function destroyQC($id)
	{
		$qc = QualityControl::with('orderPO.qualityControls')->findOrFail($id);
		$orderPO = $qc->orderPO;
		$qc->delete();

		// When last QC entry is deleted, set PO status to 2 (In Production)
		$remainingCount = $orderPO->qualityControls()->count();
		if ($remainingCount === 0) {
			$statusConfig = $this->getOrderPOStatusConfig();
			$inProductionId = $statusConfig['inProductionId'];
			if ($inProductionId !== null) {
				$orderPO->Status = $inProductionId;
				$orderPO->save();
			}
		}

		return Redirect::back()->with('success', 'QC entry deleted successfully.');
	}

	public function create(): Response
	{
		return Inertia::render('Admin/OrderManagement/CreateOrder');
	}

	public function store(Request $request)
	{
		$validated = $request->validate([
			'OrderNumber' => ['nullable', 'string', 'max:255'],
			'ClientId' => ['required', 'integer', 'exists:clients,id'],
			'QtyTotal' => ['nullable', 'integer', 'min:0'],
			'DeliveryDate' => ['nullable', 'date'],
		]);

		$order = Order::create([
			'OrderNumber' => $validated['OrderNumber'] ?? null,
			'ClientId' => $validated['ClientId'],
			'QtyTotal' => $validated['QtyTotal'] ?? null,
			'DeliveryDate' => $validated['DeliveryDate'] ?? null,
			'Status' => 1, // Set status to 1 (Newly Created)
		]);

		return Redirect::route('admin.order-management.process-order')
			->with('success', 'Order created successfully.');
	}

	/**
	 * Show the form for editing an order
	 */
	public function edit($orderId): Response
	{
		$order = Order::with('client')->findOrFail($orderId);

		return Inertia::render('Admin/OrderManagement/CreateOrder', [
			'order' => [
				'OrderId' => $order->OrderId,
				'OrderNumber' => $order->OrderNumber,
				'ClientId' => $order->ClientId,
				'ClientName' => $order->client ? $order->client->client_name : null,
				'QtyTotal' => $order->QtyTotal,
				'DeliveryDate' => $order->DeliveryDate ? $order->DeliveryDate->format('Y-m-d') : null,
				'Status' => $order->Status,
			],
			'isEdit' => true,
		]);
	}

	/**
	 * Update an existing order
	 */
	public function update(Request $request, $orderId)
	{
		$order = Order::findOrFail($orderId);

		$validated = $request->validate([
			'OrderNumber' => ['nullable', 'string', 'max:255'],
			'ClientId' => ['required', 'integer', 'exists:clients,id'],
			'QtyTotal' => ['nullable', 'integer', 'min:0'],
			'DeliveryDate' => ['nullable', 'date'],
		]);

		$order->update([
			'OrderNumber' => $validated['OrderNumber'] ?? null,
			'ClientId' => $validated['ClientId'],
			'QtyTotal' => $validated['QtyTotal'] ?? null,
			'DeliveryDate' => $validated['DeliveryDate'] ?? null,
		]);

		return Redirect::route('admin.order-management.process-order')
			->with('success', 'Order updated successfully.');
	}

	/**
	 * Get all client models by ClientId (with status label for autocomplete)
	 */
	public function getClientModels(Request $request)
	{
		$clientId = $request->input('client_id');
		
		if (!$clientId) {
			return response()->json([]);
		}

		$statusMap = [
			1 => 'New',
			2 => 'Matched',
		];

		$setting = SiteSetting::where('key', 'model_status')->first();
		if ($setting && $setting->type === 'json') {
			$decoded = json_decode($setting->value, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				foreach ($decoded as $status) {
					if (!isset($status['id'], $status['name'])) {
						continue;
					}
					$id = (int) $status['id'];
					$name = (string) $status['name'];
					// Keep compact labels for the PO autocomplete
					if ($id === 1 || stripos($name, 'new') !== false) {
						$statusMap[$id] = 'New';
					} elseif ($id === 2 || stripos($name, 'match') !== false) {
						$statusMap[$id] = 'Matched';
					} else {
						$statusMap[$id] = $name;
					}
				}
			}
		}

		$models = ClientModel::where('ClientId', $clientId)
			->select('clientmodelId', 'ModelNo', 'RefNumber', 'status')
			->orderBy('ModelNo')
			->get()
			->map(function ($model) use ($statusMap) {
				$statusId = (int) $model->status;
				return [
					'id' => $model->clientmodelId,
					'modelNo' => $model->ModelNo,
					'refNumber' => $model->RefNumber,
					'status' => $statusId,
					'statusName' => $statusMap[$statusId] ?? 'Unknown',
				];
			});

		return response()->json($models);
	}

	/**
	 * Store a new OrderPO
	 */
	public function storeOrderPO(Request $request)
	{
		// Log incoming request data for debugging
		Log::info('Order PO Store Request Received:', [
			'all_data' => $request->all(),
			'OrderId' => $request->OrderId,
			'PsNumber' => $request->PsNumber,
			'ClientModel' => $request->ClientModel,
			'RefNumber' => $request->RefNumber,
			'QtyByOrder' => $request->QtyByOrder,
			'Gold' => $request->Gold,
			'Silver' => $request->Silver,
			'DateInProduction' => $request->DateInProduction,
		]);

		try {
			$validated = $request->validate([
				'OrderId' => ['required', 'integer', 'exists:orders,OrderId'],
				'PsNumber' => ['required', 'string', 'max:255'],
				'ClientModel' => ['required', 'string', 'max:255'],
				'RefNumber' => ['required', 'string', 'max:255'],
				'QtyByOrder' => ['required', 'integer', 'min:1'],
				'Rate' => ['nullable', 'numeric', 'min:0'],
				'Gold' => ['nullable', 'integer', 'min:0'],
				'Silver' => ['nullable', 'integer', 'min:0'],
				'DateInProduction' => ['required', 'date'],
				'DateOfDelivery' => ['required', 'date'],
			]);

			Log::info('Order PO Validation Passed:', $validated);

			// Get order for validation
			$order = Order::findOrFail($validated['OrderId']);

			// Validate Qty: Cannot be 0
			if ($validated['QtyByOrder'] <= 0) {
				return redirect()->back()
					->withErrors(['QtyByOrder' => 'Qty cannot be 0.'])
					->withInput();
			}

			// Validate Qty: Cannot be more than (Total Order Qty - Sum of Qty of all existing POs)
			$totalOrderQty = $order->QtyTotal ?? 0;
			$existingPOQtysSum = OrderPO::where('OrderId', $validated['OrderId'])
				->sum('QtyByOrder') ?? 0;
			$availableQty = $totalOrderQty - $existingPOQtysSum;
			
			if ($validated['QtyByOrder'] > $availableQty) {
				return redirect()->back()
					->withErrors([
						'QtyByOrder' => "Qty cannot be more than 'Total Order Qty - Sum of Qty of all POs' ({$availableQty})."
					])
					->withInput();
			}

			// Validate Date In Production: Cannot be more than Order Date of Delivery
			if ($order->DeliveryDate) {
				$orderDeliveryDate = \Carbon\Carbon::parse($order->DeliveryDate)->startOfDay();
				$dateInProduction = \Carbon\Carbon::parse($validated['DateInProduction'])->startOfDay();
				
				if ($dateInProduction->gt($orderDeliveryDate)) {
					return redirect()->back()
						->withErrors(['DateInProduction' => 'Date In Production cannot be more than the Order Date of Delivery.'])
						->withInput();
				}
			}

			// Validate Date of Delivery: Cannot be before Date In Production
			$dateInProduction = \Carbon\Carbon::parse($validated['DateInProduction'])->startOfDay();
			$dateOfDelivery = \Carbon\Carbon::parse($validated['DateOfDelivery'])->startOfDay();
			
			if ($dateOfDelivery->lt($dateInProduction)) {
				return redirect()->back()
					->withErrors(['DateOfDelivery' => 'Date of Delivery cannot be before Date In Production.'])
					->withInput();
			}

			// Validate Date of Delivery: Cannot be more than Order Date of Delivery
			if ($order->DeliveryDate) {
				$orderDeliveryDate = \Carbon\Carbon::parse($order->DeliveryDate)->startOfDay();
				$dateOfDelivery = \Carbon\Carbon::parse($validated['DateOfDelivery'])->startOfDay();
				
				if ($dateOfDelivery->gt($orderDeliveryDate)) {
					return redirect()->back()
						->withErrors(['DateOfDelivery' => 'Date of Delivery cannot be more than the Order Date of Delivery.'])
						->withInput();
				}
			}

			// Auto-populate PS Number: Find highest PS number for this Order and add 1
			$highestPsNumber = OrderPO::where('OrderId', $validated['OrderId'])
				->whereNotNull('PsNumber')
				->where('PsNumber', '!=', '')
				->orderByRaw('CAST(PsNumber AS UNSIGNED) DESC')
				->value('PsNumber');
			
			$nextPsNumber = '1';
			if ($highestPsNumber && is_numeric($highestPsNumber)) {
				$nextPsNumber = (string)((int)$highestPsNumber + 1);
			} elseif ($highestPsNumber) {
				// If PS number is not numeric, try to extract number or default to 1
				preg_match('/\d+/', $highestPsNumber, $matches);
				if (!empty($matches)) {
					$nextPsNumber = (string)((int)$matches[0] + 1);
				}
			}

			// Get default status from OrderPos Status settings (first one, usually "Newly Added")
			$setting = SiteSetting::where('key', 'orderpos_status')->first();
			$defaultStatus = 1; // Default to ID 1 for "Newly Added"
			
			if ($setting && $setting->type === 'json') {
				$decoded = json_decode($setting->value, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
					$defaultStatus = $decoded[0]['id'] ?? 1; // Use 'id' instead of 'name', default to 1
				}
			}
			// Prepare data for insertion
			// Convert Gold and Silver to strings if they're integers (since DB column is string)
			$orderPOData = [
				'OrderId' => $validated['OrderId'],
				'PsNumber' => $nextPsNumber, // Use auto-populated PS number
				'ClientModel' => $validated['ClientModel'],
				'RefNumber' => $validated['RefNumber'],
				'QtyByOrder' => $validated['QtyByOrder'],
				'Rate' => array_key_exists('Rate', $validated) && $validated['Rate'] !== null
					? round((float) $validated['Rate'], 2)
					: null,
				'Gold' => $validated['Gold'] !== null ? (string) $validated['Gold'] : null,
				'Silver' => $validated['Silver'] !== null ? (string) $validated['Silver'] : null,
				'DateInProduction' => $validated['DateInProduction'],
				'DateOfDelivery' => $validated['DateOfDelivery'],
				'NoOfPrintOut' => 0,
				'Status' => $defaultStatus,
			];

			// Log the data being inserted for debugging
			Log::info('Creating Order PO with data:', $orderPOData);

			$orderPO = OrderPO::create($orderPOData);

			// Log successful creation
			Log::info('Order PO created successfully with ID: ' . $orderPO->id);

			return Redirect::route('admin.order-management.process-order')
				->with('success', 'Order PO added successfully.');
		} catch (\Illuminate\Validation\ValidationException $e) {
			Log::error('Validation error creating Order PO:', [
				'errors' => $e->errors(),
				'request_data' => $request->all()
			]);
			return redirect()->back()
				->withErrors($e->errors())
				->withInput();
		} catch (\Exception $e) {
			Log::error('Error creating Order PO: ' . $e->getMessage(), [
				'exception' => $e,
				'trace' => $e->getTraceAsString(),
				'validated_data' => $validated ?? null,
				'request_data' => $request->all()
			]);
			return redirect()->back()
				->withErrors(['error' => 'Failed to add Order PO: ' . $e->getMessage()])
				->withInput();
		}
	}

	/**
	 * Update order status with business rules validation
	 */
	public function updateStatus(Request $request, $orderId)
	{
		$validated = $request->validate([
			'status' => ['required', 'integer'],
		]);

		$order = Order::findOrFail($orderId);
		$newStatus = (int) $validated['status'];
		$currentStatus = is_numeric($order->Status) ? (int) $order->Status : null;

		// Rule 1: Prevent direct transition from "Newly Created" (1) to "Completed" (3)
		if ($currentStatus === 1 && $newStatus === 3) {
			return response()->json([
				'success' => false,
				'message' => "Status cannot be changed to 'Completed'. It can only be changed to 'In Production'.",
			], 422);
		}

		// Rule 2: Validate "In Production" transition (status = 2)
		if ($newStatus === 2) {
			// Check if Order has any OrderPOs
			$orderPOs = $order->orderPOs;
			if ($orderPOs->isEmpty()) {
				return response()->json([
					'success' => false,
					'message' => 'Cannot change status to "In Production". Order must have at least one Purchase Order.',
				], 422);
			}

			// Calculate sum of all OrderPO QtyByOrder values
			$totalPOQty = $orderPOs->sum('QtyByOrder') ?? 0;
			$orderQty = $order->QtyTotal ?? 0;

			// Compare with Order QtyTotal
			if ($totalPOQty < $orderQty) {
				return response()->json([
					'success' => false,
					'message' => 'The Total Qty of the Purchase Orders is less than the Order Quantity.',
				], 422);
			}

			// Rule 3: Update all OrderPOs to "In Production" (id 2 from orderpos_status)
			$statusConfig = $this->getOrderPOStatusConfig();
			$inProductionId = $statusConfig['inProductionId'];
			$orderPOs->each(function ($orderPO) use ($inProductionId) {
				$orderPO->Status = $inProductionId;
				$orderPO->save();
			});

			$order->Status = $newStatus;
			$order->save();

			return response()->json([
				'success' => true,
				'message' => 'Status updated successfully. Status of all POs have been changed to \'In Production\'.',
			]);
		}

		// Rule 4: Validate "Completed" transition (status = 3)
		if ($newStatus === 3) {
			// Only allow from "In Production" (2)
			if ($currentStatus !== 2) {
				return response()->json([
					'success' => false,
					'message' => 'Order status can only be changed to "Completed" from "In Production".',
				], 422);
			}

			// Check all OrderPOs have status "Produced" (id 4 from orderpos_status)
			$statusConfig = $this->getOrderPOStatusConfig();
			$producedId = $statusConfig['producedId'];
			$orderPOs = $order->orderPOs;
			$nonProducedPOs = $orderPOs->filter(function ($orderPO) use ($producedId) {
				return (int) $orderPO->Status !== (int) $producedId;
			});

			if ($nonProducedPOs->isNotEmpty()) {
				return response()->json([
					'success' => false,
					'message' => 'All Purchase Orders must be \'QC Completed\' before Order can be marked as \'QC Done\'.',
				], 422);
			}

			$order->Status = $newStatus;
			$order->save();

			return response()->json([
				'success' => true,
				'message' => 'Order status updated successfully.',
			]);
		}

		// For any other status transitions (e.g., back to "Newly Created"), allow it
		$order->Status = $newStatus;
		$order->save();

		return response()->json([
			'success' => true,
			'message' => 'Order status updated successfully.',
		]);
	}

	/**
	 * Update an existing OrderPO
	 */
	public function updateOrderPO(Request $request, $id)
	{
		// Log incoming request data for debugging
		Log::info('Order PO Update Request:', [
			'id' => $id,
			'all_data' => $request->all(),
		]);

		try {
			$orderPO = OrderPO::findOrFail($id);

			$validated = $request->validate([
				'OrderId' => ['required', 'integer', 'exists:orders,OrderId'],
				'PsNumber' => ['required', 'string', 'max:255'],
				'ClientModel' => ['required', 'string', 'max:255'],
				'RefNumber' => ['required', 'string', 'max:255'],
				'QtyByOrder' => ['required', 'integer', 'min:1'],
				'Rate' => ['nullable', 'numeric', 'min:0'],
				'Gold' => ['nullable', 'integer', 'min:0'],
				'Silver' => ['nullable', 'integer', 'min:0'],
				'DateInProduction' => ['required', 'date'],
				'DateOfDelivery' => ['required', 'date'],
			]);

			// Get order for validation
			$order = Order::findOrFail($validated['OrderId']);

			// Validate Qty: Cannot be 0
			if ($validated['QtyByOrder'] <= 0) {
				return redirect()->back()
					->withErrors(['QtyByOrder' => 'Qty cannot be 0.'])
					->withInput();
			}

			// Validate Qty: Cannot be more than (Total Order Qty - Sum of Qty of all existing POs excluding current PO)
			$totalOrderQty = $order->QtyTotal ?? 0;
			$existingPOQtysSum = OrderPO::where('OrderId', $validated['OrderId'])
				->where('id', '!=', $id) // Exclude current PO being updated
				->sum('QtyByOrder') ?? 0;
			$availableQty = $totalOrderQty - $existingPOQtysSum;
			
			if ($validated['QtyByOrder'] > $availableQty) {
				return redirect()->back()
					->withErrors([
						'QtyByOrder' => "Qty cannot be more than 'Total Order Qty - Sum of Qty of all POs' ({$availableQty})."
					])
					->withInput();
			}

			// Validate Date In Production: Cannot be more than Order Date of Delivery
			if ($order->DeliveryDate) {
				$orderDeliveryDate = \Carbon\Carbon::parse($order->DeliveryDate)->startOfDay();
				$dateInProduction = \Carbon\Carbon::parse($validated['DateInProduction'])->startOfDay();
				
				if ($dateInProduction->gt($orderDeliveryDate)) {
					return redirect()->back()
						->withErrors(['DateInProduction' => 'Date In Production cannot be more than the Order Date of Delivery.'])
						->withInput();
				}
			}

			// Validate Date of Delivery: Cannot be before Date In Production
			$dateInProduction = \Carbon\Carbon::parse($validated['DateInProduction'])->startOfDay();
			$dateOfDelivery = \Carbon\Carbon::parse($validated['DateOfDelivery'])->startOfDay();
			
			if ($dateOfDelivery->lt($dateInProduction)) {
				return redirect()->back()
					->withErrors(['DateOfDelivery' => 'Date of Delivery cannot be before Date In Production.'])
					->withInput();
			}

			// Validate Date of Delivery: Cannot be more than Order Date of Delivery
			if ($order->DeliveryDate) {
				$orderDeliveryDate = \Carbon\Carbon::parse($order->DeliveryDate)->startOfDay();
				$dateOfDelivery = \Carbon\Carbon::parse($validated['DateOfDelivery'])->startOfDay();
				
				if ($dateOfDelivery->gt($orderDeliveryDate)) {
					return redirect()->back()
						->withErrors(['DateOfDelivery' => 'Date of Delivery cannot be more than the Order Date of Delivery.'])
						->withInput();
				}
			}

			// Validate Gold + Silver: Cannot be more than Qty
			$gold = $validated['Gold'] ?? 0;
			$silver = $validated['Silver'] ?? 0;
			$totalGoldSilver = $gold + $silver;
			
			if ($totalGoldSilver > $validated['QtyByOrder']) {
				return redirect()->back()
					->withErrors([
						'Gold' => "The sum of Gold ({$gold}) and Silver ({$silver}) cannot be more than Qty ({$validated['QtyByOrder']}).",
						'Silver' => "The sum of Gold ({$gold}) and Silver ({$silver}) cannot be more than Qty ({$validated['QtyByOrder']})."
					])
					->withInput();
			}

			// Prepare data for update
			$orderPOData = [
				'OrderId' => $validated['OrderId'],
				'PsNumber' => $validated['PsNumber'] ?? null,
				'ClientModel' => $validated['ClientModel'],
				'RefNumber' => $validated['RefNumber'],
				'QtyByOrder' => $validated['QtyByOrder'],
				'Rate' => array_key_exists('Rate', $validated) && $validated['Rate'] !== null
					? round((float) $validated['Rate'], 2)
					: null,
				'Gold' => $validated['Gold'] !== null ? (string) $validated['Gold'] : null,
				'Silver' => $validated['Silver'] !== null ? (string) $validated['Silver'] : null,
				'DateInProduction' => $validated['DateInProduction'],
				'DateOfDelivery' => $validated['DateOfDelivery'],
			];

			Log::info('Updating Order PO with data:', $orderPOData);

			$orderPO->update($orderPOData);

			Log::info('Order PO updated successfully with ID: ' . $orderPO->id);

			return Redirect::route('admin.order-management.process-order')
				->with('success', 'Order PO updated successfully.');
		} catch (\Illuminate\Validation\ValidationException $e) {
			Log::error('Validation error updating Order PO:', [
				'errors' => $e->errors(),
				'request_data' => $request->all()
			]);
			return redirect()->back()
				->withErrors($e->errors())
				->withInput();
		} catch (\Exception $e) {
			Log::error('Error updating Order PO: ' . $e->getMessage(), [
				'exception' => $e,
				'trace' => $e->getTraceAsString(),
				'request_data' => $request->all()
			]);
			return redirect()->back()
				->withErrors(['error' => 'Failed to update Order PO: ' . $e->getMessage()])
				->withInput();
		}
	}

	/**
	 * Update OrderPO status
	 */
	public function updateOrderPOStatus(Request $request, $id)
	{
		$validated = $request->validate([
			'status' => ['required'], // Can be string name or integer ID
		]);

		$orderPO = OrderPO::findOrFail($id);

		$statusConfig = $this->getOrderPOStatusConfig();
		$allowedIds = $statusConfig['allowedIdsForProductionPage'];
		$producedId = $statusConfig['producedId'];
		$completedQcStatusId = $statusConfig['completedQcStatusId'];
		$completedQcStatusName = $statusConfig['completedQcStatusName'] ?? '';

		$statusValue = (int) $validated['status'];

		if (!in_array($statusValue, $allowedIds)) {
			return response()->json([
				'success' => false,
				'message' => 'Invalid status.',
			], 422);
		}

		// Produced (4) only when: sum(QC Qty) = PO Qty AND all QC entries have status 3 (Completed)
		if ($producedId !== null && $statusValue === $producedId) {
			$orderPO->load('qualityControls');
			$qcEntries = $orderPO->qualityControls;
			$poQty = (int) ($orderPO->QtyByOrder ?? 0);
			$qcQtySum = $qcEntries->sum('Qty');

			if ($qcQtySum !== $poQty) {
				return response()->json([
					'success' => false,
					'message' => 'Cannot set status to Produced. Sum of QC quantities (' . $qcQtySum . ') must equal PO quantity (' . $poQty . ').',
				], 422);
			}

			$incompleteCount = $qcEntries->filter(function ($qc) use ($completedQcStatusId, $completedQcStatusName) {
				$qcStatus = trim($qc->QC_status ?? '');
				$isCompletedById = is_numeric($qcStatus) && (int) $qcStatus === $completedQcStatusId;
				$isCompletedByName = $completedQcStatusName !== '' && strcasecmp($qcStatus, $completedQcStatusName) === 0;
				return !$isCompletedById && !$isCompletedByName;
			})->count();
			if ($incompleteCount > 0) {
				return response()->json([
					'success' => false,
					'message' => 'Cannot set status to Produced until all QC entries have status Completed.',
				], 422);
			}
		}

		$orderPO->Status = $statusValue;
		$orderPO->save();

		return response()->json([
			'success' => true,
			'message' => 'Order PO status updated successfully.',
		]);
	}

	/**
	 * Get Order PO status config from orderpos_status (IDs only).
	 * Convention: index 0 = Newly Added, 1 = In Production (2), 2 = QC Started (3), 3 = Produced (4).
	 */
	private function getOrderPOStatusConfig(): array
	{
		$setting = SiteSetting::where('key', 'orderpos_status')->first();
		$orderPosStatuses = [];
		if ($setting && $setting->type === 'json') {
			$decoded = json_decode($setting->value, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				$orderPosStatuses = array_values($decoded);
			}
		}

		$inProductionId = isset($orderPosStatuses[1]['id']) ? (int) $orderPosStatuses[1]['id'] : 2;
		$qcStartedId = isset($orderPosStatuses[2]['id']) ? (int) $orderPosStatuses[2]['id'] : 3;
		$producedId = isset($orderPosStatuses[3]['id']) ? (int) $orderPosStatuses[3]['id'] : 4;

		$allowedIdsForProductionPage = array_filter([
			$inProductionId,
			$qcStartedId,
			$producedId,
		]);

		// Completed QC status ID from quality_control_status (index 2 = Completed)
		$completedQcStatusId = 3;
		$completedQcStatusName = null;
		$qcSetting = SiteSetting::where('key', 'quality_control_status')->first();
		if ($qcSetting && $qcSetting->type === 'json') {
			$qcDecoded = json_decode($qcSetting->value, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($qcDecoded) && isset($qcDecoded[2])) {
				$completedQcStatusId = (int) ($qcDecoded[2]['id'] ?? 3);
				$completedQcStatusName = trim($qcDecoded[2]['name'] ?? '');
			}
		}

		return [
			'allowedIdsForProductionPage' => $allowedIdsForProductionPage,
			'inProductionId' => $inProductionId,
			'producedId' => $producedId,
			'qcStartedId' => $qcStartedId,
			'completedQcStatusId' => $completedQcStatusId,
			'completedQcStatusName' => $completedQcStatusName,
		];
	}

	/**
	 * Get full QC reasons list [{id, name}] from site_settings
	 */
	private function getQcReasonsList(): array
	{
		$setting = SiteSetting::where('key', 'qc_reasons')->first();
		if (!$setting || $setting->type !== 'json') {
			return [];
		}
		$decoded = json_decode($setting->value, true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
			return [];
		}
		return array_values(array_filter($decoded, fn ($r) => isset($r['id']) && isset($r['name'])));
	}

	/**
	 * Get QC reason id=>name map from site_settings
	 */
	private function getQcReasonMap(): array
	{
		$setting = SiteSetting::where('key', 'qc_reasons')->first();
		if (!$setting || $setting->type !== 'json') {
			return [];
		}
		$decoded = json_decode($setting->value, true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
			return [];
		}
		$map = [];
		foreach ($decoded as $r) {
			$id = $r['id'] ?? null;
			$name = $r['name'] ?? '';
			if ($id !== null) {
				$map[(int) $id] = $name;
			}
		}
		return $map;
	}

	/**
	 * Normalize QC Gold / Silver DB values to a non-negative integer (handles null, '', numeric strings).
	 */
	private function qcMetalQtyToInt(mixed $value): int
	{
		if ($value === null || $value === '') {
			return 0;
		}
		if (is_bool($value)) {
			return $value ? 1 : 0;
		}
		if (is_int($value) || is_float($value)) {
			return max(0, (int) $value);
		}
		if (is_string($value)) {
			$trim = trim($value);
			if ($trim === '' || ! is_numeric($trim)) {
				return 0;
			}

			return max(0, (int) $trim);
		}

		return 0;
	}

	/**
	 * Total quantity accepted in QC, derived from qc_register rows.
	 * When the register fully accounts for QC qty (sum equals QC qty), accepted = QC qty minus quantities
	 * assigned to non-pass reasons (all defect-style reasons count against accepted).
	 * When allocation is partial, show the sum of rows whose reason is treated as pass/accepted only.
	 *
	 * @param  \Illuminate\Support\Collection<int, QcRegister>  $registers
	 */
	private function resolveQcQtyAccepted($registers, array $reasonMap, int $qcMaxQty, int $registerTotal): ?int
	{
		if ($qcMaxQty <= 0 || $registerTotal <= 0) {
			return null;
		}

		$acceptedPassSum = 0;
		$nonPassSum = 0;
		foreach ($registers as $r) {
			$qty = (int) ($r->qty ?? 0);
			$reasonId = (int) ($r->qc_reason_id ?? 0);
			$name = (string) ($reasonMap[$reasonId] ?? '');
			if ($this->isQcReasonNameAcceptedPass($name)) {
				$acceptedPassSum += $qty;
			} else {
				$nonPassSum += $qty;
			}
		}

		if ($registerTotal === $qcMaxQty) {
			return max(0, $qcMaxQty - $nonPassSum);
		}

		return $acceptedPassSum > 0 ? $acceptedPassSum : null;
	}

	/**
	 * Resolve an existing accepted/pass QC reason id, or create "Accepted" if none exists.
	 */
	private function ensureAcceptedQcReasonId(): int
	{
		$existing = $this->resolveAcceptedQcReasonId();
		if ($existing !== null) {
			return $existing;
		}

		$setting = SiteSetting::where('key', 'qc_reasons')->first();
		$reasons = [];
		if ($setting && $setting->type === 'json') {
			$decoded = json_decode($setting->value, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				$reasons = $decoded;
			}
		}

		$maxId = 0;
		foreach ($reasons as $r) {
			if (isset($r['id']) && (int) $r['id'] > $maxId) {
				$maxId = (int) $r['id'];
			}
		}

		$newId = $maxId + 1;
		$reasons[] = ['id' => $newId, 'name' => 'Accepted'];

		SiteSetting::updateOrCreate(
			['key' => 'qc_reasons'],
			['value' => json_encode($reasons), 'type' => 'json']
		);

		return $newId;
	}

	/**
	 * Find the preferred accepted/pass QC reason id from configured reasons.
	 */
	private function resolveAcceptedQcReasonId(): ?int
	{
		$reasons = $this->getQcReasonsList();
		$fallback = null;

		foreach ($reasons as $r) {
			$name = (string) ($r['name'] ?? '');
			$id = (int) ($r['id'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			if (mb_strtolower(trim($name)) === 'accepted') {
				return $id;
			}
			if ($fallback === null && $this->isQcReasonNameAcceptedPass($name)) {
				$fallback = $id;
			}
		}

		return $fallback;
	}

	/**
	 * QC reasons that represent accepted / passed quantity (not defects).
	 */
	private function isQcReasonNameAcceptedPass(string $reasonName): bool
	{
		$normalized = mb_strtolower(trim($reasonName));
		if ($normalized === '') {
			return false;
		}

		$exact = [
			'accepted',
			'qty accepted',
			'qc accepted',
			'passed',
			'pass',
			'ok',
			'approved',
			'good',
			'no issue',
			'no defect',
			'none',
			'nil',
			'clear',
		];

		if (in_array($normalized, $exact, true)) {
			return true;
		}

		// "Accepted …", "QC Accepted", etc.
		if (preg_match('/^(qc\s+)?accepted\b/u', $normalized)) {
			return true;
		}

		return false;
	}

	/**
	 * Whether a QC row is in "completed" state (matches ID, configured name, or contains "completed").
	 */
	private function isQualityControlEntryCompleted(QualityControl $qc, array $statusConfig): bool
	{
		$completedQcStatusId = (int) ($statusConfig['completedQcStatusId'] ?? 3);
		$completedQcStatusName = trim((string) ($statusConfig['completedQcStatusName'] ?? ''));
		$qcStatusRaw = trim((string) ($qc->QC_status ?? ''));
		$isCompletedById = is_numeric($qcStatusRaw) && (int) $qcStatusRaw === $completedQcStatusId;
		$isCompletedByName = $completedQcStatusName !== '' && strcasecmp($qcStatusRaw, $completedQcStatusName) === 0;
		$isCompletedByText = stripos($qcStatusRaw, 'completed') !== false;

		return $isCompletedById || $isCompletedByName || $isCompletedByText;
	}

	/**
	 * @param  iterable<int, QcRegister>  $registers
	 * @return array<int, array{reason_id: ?int, reason_name: string, qty: int}>
	 */
	private function aggregateQcRegistersToRows(iterable $registers): array
	{
		$reasonMap = $this->getQcReasonMap();
		$distribution = [];
		foreach ($registers as $r) {
			$reasonId = (int) ($r->qc_reason_id ?? 0);
			$key = $reasonId > 0 ? (string) $reasonId : 'unknown';
			if (! isset($distribution[$key])) {
				$distribution[$key] = [
					'reason_id' => $reasonId > 0 ? $reasonId : null,
					'reason_name' => $reasonId > 0
						? ($reasonMap[$reasonId] ?? ('Reason #'.$reasonId))
						: 'Unknown',
					'qty' => 0,
				];
			}
			$distribution[$key]['qty'] += (int) ($r->qty ?? 0);
		}

		$rows = array_values($distribution);
		usort($rows, fn ($a, $b) => $b['qty'] <=> $a['qty']);

		return $rows;
	}

	/**
	 * Get QC status ID by index from quality_control_status (0=Pending, 1=In Progress, 2=Completed)
	 */
	private function getQCStatusIdByIndex(int $index): ?int
	{
		$qcSetting = SiteSetting::where('key', 'quality_control_status')->first();
		if (!$qcSetting || $qcSetting->type !== 'json') {
			return null;
		}
		$decoded = json_decode($qcSetting->value, true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || !isset($decoded[$index]['id'])) {
			return null;
		}
		return (int) $decoded[$index]['id'];
	}

	/**
	 * Generate and view OrderPO PDF
	 */
	public function viewPOPDF($id)
	{
		try {
			$orderPO = OrderPO::with('order.client')->findOrFail($id);
			$order = $orderPO->order;
			if (!$order) {
				return redirect()->back()
					->withErrors(['error' => 'Order not found for this Order PO.']);
			}

			// Match OrderPO.ClientModel to ClientModel.ModelNo (exact match)
			$clientModel = null;
			if ($orderPO->ClientModel) {
				$clientModel = ClientModel::where('ModelNo', $orderPO->ClientModel)->first();
				
				if (!$clientModel) {
					Log::warning('ClientModel not found for ModelNo: ' . $orderPO->ClientModel);
				}
			}

			// Increment sequential print number
			$currentPrintOut = $orderPO->NoOfPrintOut ?? 0;
			$printNumber = $currentPrintOut + 1;
			$orderPO->NoOfPrintOut = $printNumber;
			$orderPO->save();

			// Helper function to safely get value or return '-'
			$getValue = function($value) {
				return $value ?: '-';
			};

			// Delivery date: use PO DateOfDelivery if set, else Order DeliveryDate
			$poDeliveryDate = $orderPO->DateOfDelivery ? $orderPO->DateOfDelivery->format('d-M-Y') : null;
			$orderDeliveryDate = $order->DeliveryDate ? $order->DeliveryDate->format('d-M-Y') : null;
			$data = [
				// Basic OrderPO and Order data
				'printNumber' => $printNumber,
				'clientModel' => $orderPO->ClientModel ?: '-',
				'clientName' => $order->client ? $order->client->client_name : '-',
				'printDate' => now()->format('d-M-Y'),
				'refNumber' => $orderPO->RefNumber ?: '-',
				'deliveryDateLabel' => $poDeliveryDate !== null ? 'PO Delivery Date' : 'Order Delivery Date',
				'deliveryDate' => $poDeliveryDate ?? $orderDeliveryDate ?? '-',
				'orderId' => $order->OrderId,
				'refOrder' => $order->OrderNumber ?: '-',
				'psNumber' => $orderPO->PsNumber ?: '-',
				'quantity' => $orderPO->QtyByOrder ?: '-',
				'gold' => $orderPO->Gold ?: '-',
				'silver' => $orderPO->Silver ?: '-',
				'picturePath' => null,
				'technicalSpecs' => '',
				'oemReference' => '-',
			];

			// If ClientModel found, fetch all related data
			if ($clientModel) {
				$data['oemReference'] = $clientModel->OemReference ?: '-';

				// Special (SpecialId1 + SpecialId2)
				$special1 = $clientModel->SpecialId1 ? Special::find($clientModel->SpecialId1) : null;
				$special2 = $clientModel->SpecialId2 ? Special::find($clientModel->SpecialId2) : null;
				$specialParts = array_filter([
					$special1 ? $special1->Description : null,
					$special2 ? $special2->Description : null,
				]);
				$data['special'] = !empty($specialParts) ? $specialParts : [];

				// Lining
				$lining = $clientModel->LiningId ? Lining::find($clientModel->LiningId) : null;
				$data['lining'] = $lining ? $lining->Name : '-';

				// Leather
				$leather = $clientModel->LeatherId ? Leather::find($clientModel->LeatherId) : null;
				$data['leather'] = $leather ? $leather->Name : '-';

				// Leather Colour
				$leatherColour = $clientModel->LeatherColourId ? Colour::find($clientModel->LeatherColourId) : null;
				$data['colour'] = $leatherColour ? $leatherColour->ColourName : '-';

				// End Width
				$endWidth = $clientModel->EndWidthId ? EndWidth::find($clientModel->EndWidthId) : null;
				$data['width'] = $endWidth ? $endWidth->EndWidthSize : '-';

				// Strap Length Long
				$strapLengthLong = $clientModel->StrapLengthLongPartId ? StrapLengthLongPart::find($clientModel->StrapLengthLongPartId) : null;
				$data['strapLengthLong'] = $strapLengthLong ? $strapLengthLong->LongPart : '-';

				// Strap Length Short
				$strapLengthShort = $clientModel->StrapLengthShortPartId ? StrapLengthShortPart::find($clientModel->StrapLengthShortPartId) : null;
				$data['strapLengthShort'] = $strapLengthShort ? $strapLengthShort->ShortPart : '-';

				// Buckle
				$buckle = $clientModel->BuckleId ? Buckle::find($clientModel->BuckleId) : null;
				$data['buckle'] = $buckle ? $buckle->BuckleType : '-';

				// Buckle Width
				$buckleWidth = $clientModel->BuckleWidthId ? BuckleWidth::find($clientModel->BuckleWidthId) : null;
				$data['buckleWidth'] = $buckleWidth ? $buckleWidth->BuckleWidthSize : '-';

				// Design Name + Design Description
				$designName = $clientModel->DesignNameId ? DesignName::find($clientModel->DesignNameId) : null;
				$designDescription = $clientModel->DesignDescriptionId ? DesignDescription::find($clientModel->DesignDescriptionId) : null;
				$designParts = array_filter([
					$designName ? $designName->Name : null,
					$designDescription ? $designDescription->Description : null,
				]);
				$data['design'] = $designName ? $designName->Name : '-';

				// Thickness: Extract from DesignDescription if it contains thickness pattern
				$thickness = '-';
				if ($designDescription && $designDescription->Description) {
					// Look for pattern like "4.5 - 3.5mm" or similar
					$thickness = $designDescription->Description;
				}
				$data['thickness'] = $thickness;

				// Loop Band (Loop Size)
				$loopBand = $clientModel->LoopBandId ? LoopBand::find($clientModel->LoopBandId) : null;
				$data['loopSize'] = $loopBand ? $loopBand->LoopBandSize : '-';

				// Big Loop Qty & Small Loop Qty (× total PO quantity)
				$qty = (int) ($orderPO->QtyByOrder ?: 0);
				$data['bigLoop'] = $clientModel->BigLoopQty
					? ((int) $clientModel->BigLoopQty * $qty)
					: '-';
				$data['smallLoop'] = $clientModel->SmallLoopQty
					? ((int) $clientModel->SmallLoopQty * $qty)
					: '-';

				// Top Thread Colour
				$topThreadColour = $clientModel->TopThreadColourId ? Colour::find($clientModel->TopThreadColourId) : null;
				$data['threadColourTop'] = $topThreadColour ? $topThreadColour->ColourName : '-';

				// Top Thread Size
				$topThreadSize = $clientModel->TopThreadSizeId ? ThreadSize::find($clientModel->TopThreadSizeId) : null;
				$data['threadSizeTop'] = $topThreadSize ? $topThreadSize->ThreadSize : '';

				// Bottom Thread Colour
				$bottomThreadColour = $clientModel->BottomThreadColourId ? Colour::find($clientModel->BottomThreadColourId) : null;
				$data['threadColourBottom'] = $bottomThreadColour ? $bottomThreadColour->ColourName : '-';

				// Bottom Thread Size
				$bottomThreadSize = $clientModel->BottomThreadSizeId ? ThreadSize::find($clientModel->BottomThreadSizeId) : null;
				$data['threadSizeBottom'] = $bottomThreadSize ? $bottomThreadSize->ThreadSize : '';

				// Varnish Colour
				$varnishColour = $clientModel->VarnishColourId ? Colour::find($clientModel->VarnishColourId) : null;
				$data['varnish'] = $varnishColour ? $varnishColour->ColourName : '-';

				// Reinforcement
				$data['reinforcement'] = $clientModel->ReinforceType ? ($clientModel->ReinforceType == 'YES' || $clientModel->ReinforceType == '1' ? 'YES' : ($clientModel->ReinforceType == 'NO' || $clientModel->ReinforceType == '0' ? 'NO' : $clientModel->ReinforceType)) : '-';

				// Stamping Short
				$stampingShort = $clientModel->StampingShortId ? StampingShort::find($clientModel->StampingShortId) : null;
				$data['stampingShort'] = $stampingShort ? $stampingShort->Description : '-';

				// Stamping Long
				$stampingLong = $clientModel->StampingLongId ? StampingLong::find($clientModel->StampingLongId) : null;
				$data['stampingLong'] = $stampingLong ? $stampingLong->Description : '-';

				// Note Info Line 1 & 2
				$noteInfoLine1 = $clientModel->NoteInfoLine1Id ? NoteInfoLine1::find($clientModel->NoteInfoLine1Id) : null;
				$noteInfoLine2 = $clientModel->NoteInfoLine2Id ? NoteInfoLine2::find($clientModel->NoteInfoLine2Id) : null;
				$noteParts = array_filter([
					$noteInfoLine1 ? $noteInfoLine1->Line1 : null,
					$noteInfoLine2 ? $noteInfoLine2->Line2 : null,
				]);
				$data['notes'] = !empty($noteParts) ? $noteParts : [];

				// First Cutting Tool
				$firstCuttingTool = $clientModel->FirstCuttingToolId ? Tool::find($clientModel->FirstCuttingToolId) : null;
				$data['firstCuttingTool'] = $firstCuttingTool ? $firstCuttingTool->Name : '-';

				// Inside Cutting Tool
				$insideCuttingTool = $clientModel->InsideCuttingToolId ? Tool::find($clientModel->InsideCuttingToolId) : null;
				$data['insideCuttingTool'] = $insideCuttingTool ? $insideCuttingTool->Name : '-';

				// Loop Cutting Tool
				$loopCuttingTool = $clientModel->LoopCuttingToolId ? Tool::find($clientModel->LoopCuttingToolId) : null;
				$data['loopCuttingTool'] = $loopCuttingTool ? $loopCuttingTool->Name : '-';

				// Loop Folding Tool
				$loopFoldingTool = $clientModel->LoopFoldingToolId ? Tool::find($clientModel->LoopFoldingToolId) : null;
				$data['loopFoldingTool'] = $loopFoldingTool ? $loopFoldingTool->Name : '-';

				// Strap Picture - Convert to base64 for reliable embedding in PDF
				$picturePath = null;
				$pictureFileName = null;
				if ($clientModel->StrapPictureId) {
					$strapPicture = StrapPicture::find($clientModel->StrapPictureId);
					if ($strapPicture && $strapPicture->Name) {
						$pictureFileName = $strapPicture->Name; // Store filename for display
						$absolutePath = storage_path('app/private/strappictures/' . $strapPicture->Name);
						if (file_exists($absolutePath)) {
							try {
								// Read image file and convert to base64
								$imageData = file_get_contents($absolutePath);
								$imageInfo = getimagesize($absolutePath);
								
								if ($imageData && $imageInfo) {
									// Determine MIME type
									$mimeType = $imageInfo['mime'];
									
									// Convert to base64 data URI
									$base64 = base64_encode($imageData);
									$picturePath = 'data:' . $mimeType . ';base64,' . $base64;
								}
							} catch (\Exception $e) {
								\Log::warning('Failed to read strap picture for PDF: ' . $e->getMessage());
							}
						}
					}
				}
				$data['picturePath'] = $picturePath;
				$data['pictureFileName'] = $pictureFileName;
			} else {
				// Set all fields to '-' if ClientModel not found
				$data['picturePath'] = null;
				$data['pictureFileName'] = null;
				$data['special'] = [];
				$data['lining'] = '-';
				$data['leather'] = '-';
				$data['colour'] = '-';
				$data['width'] = '-';
				$data['strapLengthLong'] = '-';
				$data['strapLengthShort'] = '-';
				$data['buckle'] = '-';
				$data['buckleWidth'] = '-';
				$data['design'] = '-';
				$data['thickness'] = '-';
				$data['loopSize'] = '-';
				$data['bigLoop'] = '-';
				$data['smallLoop'] = '-';
				$data['threadColourTop'] = '-';
				$data['threadSizeTop'] = '';
				$data['threadColourBottom'] = '-';
				$data['threadSizeBottom'] = '';
				$data['varnish'] = '-';
				$data['reinforcement'] = '-';
				$data['stampingShort'] = '-';
				$data['stampingLong'] = '-';
				$data['notes'] = [];
				$data['firstCuttingTool'] = '-';
				$data['insideCuttingTool'] = '-';
				$data['loopCuttingTool'] = '-';
				$data['loopFoldingTool'] = '-';
			}

			// Render Blade template to HTML
			$html = view('pdf.order-po-production-slip', $data)->render();

			// Generate PDF using wkhtmltopdf (Snappy)
			// wkhtmltopdf uses WebKit engine, which has excellent CSS support
			$pdf = PDF::loadHTML($html)
				->setOption('page-size', 'A4')
				->setOption('orientation', 'Portrait')
				->setOption('margin-top', '2mm')
				->setOption('margin-right', '2mm')
				->setOption('margin-bottom', '2mm')
				->setOption('margin-left', '2mm')
				->setOption('print-media-type', true) // Use print media type
				->setOption('enable-local-file-access', true) // Allow local file access for images
				->setOption('no-outline', true) // No PDF outline
				->setOption('disable-smart-shrinking', false); // Enable smart shrinking to fit content on one page

			// Output PDF inline - filename: PO-{id}-CM-{client model}.pdf
			$clientModel = $orderPO->ClientModel ? preg_replace('/[^a-zA-Z0-9_-]/', '-', $orderPO->ClientModel) : 'unknown';
			$filename = 'PO-' . $id . '-CM-' . $clientModel . '.pdf';
			return $pdf->inline($filename);
			
		} catch (\Exception $e) {
			Log::error('Error generating OrderPO PDF: ' . $e->getMessage(), [
				'exception' => $e,
				'trace' => $e->getTraceAsString(),
				'orderPOId' => $id ?? null
			]);
			return redirect()->back()
				->withErrors(['error' => 'Failed to generate PDF: ' . $e->getMessage()]);
		}
	}

	/**
	 * Delete an OrderPO
	 */
	public function destroyOrderPO($id)
	{
		try {
			Log::info('Deleting Order PO with ID: ' . $id);

			$orderPO = OrderPO::findOrFail($id);
			$orderPO->delete();

			Log::info('Order PO deleted successfully with ID: ' . $id);

			return Redirect::route('admin.order-management.process-order')
				->with('success', 'Order PO deleted successfully.');
		} catch (\Exception $e) {
			Log::error('Error deleting Order PO: ' . $e->getMessage(), [
				'exception' => $e,
				'trace' => $e->getTraceAsString()
			]);
			return redirect()->back()
				->withErrors(['error' => 'Failed to delete Order PO: ' . $e->getMessage()]);
		}
	}
}

