<?php

namespace App\Http\Controllers;

use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\Product;
use App\Models\DeviceToken;
use App\Models\Notification as AppNotification;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductOrderController extends Controller
{
    /** @var FirebaseService */
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /* ---------------------------------------------------------------------
     | Helper: Create DB + Push Notification
     |---------------------------------------------------------------------*/
    private function notifyUser(int $userId, int $orderId, string $title, string $message): void
    {
        // Save notification inside DB
        AppNotification::create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'title'   => $title,
            'message' => $message,
            'type'    => 'order',
        ]);

        // Retrieve every device token for this user
        $tokens = DeviceToken::where('user_id', $userId)->pluck('device_token');

        foreach ($tokens as $token) {
            // Fire & forget – we don't mind individual failures
            try {
                $this->firebase->sendNotificationToDevice($token, $title, $message);
            } catch (\Throwable $e) {
                \Log::warning('Firebase send failed', [
                    'user_id' => $userId,
                    'token'   => $token,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /* ---------------------------------------------------------------------
     | Order creation – unchanged (just imports)
     |---------------------------------------------------------------------*/
    public function store(Request $request)
    {
        $request->validate([
            'buyer_id'              => 'required|exists:users,id',
            'delivery_address'      => 'required|string',
            'estimated_delivery'    => 'nullable|date',
            'items'                 => 'required|array',
            'items.*.product_id'    => 'required|exists:products,product_id',
            'items.*.quantity'      => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // Group items by store so each seller gets its own order
            $itemsByStore = [];
            foreach ($request->items as $item) {
                $product = Product::with('store')->findOrFail($item['product_id']);

                if (!$product->store || !$product->store->owner_id) {
                    throw new \Exception("Seller information missing for product: {$product->product_name}");
                }
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->product_name}");
                }

                $storeId  = $product->store_id;
                $sellerId = $product->store->owner_id;

                $itemsByStore[$storeId]['seller_id']    = $sellerId;
                $itemsByStore[$storeId]['total_amount'] = ($itemsByStore[$storeId]['total_amount'] ?? 0) + ($product->price * $item['quantity']);
                $itemsByStore[$storeId]['items'][]      = [
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'seller_id'  => $sellerId,
                    'product'    => $product,
                ];
            }

            $createdOrders = [];

            foreach ($itemsByStore as $storeData) {
                $order = ProductOrder::create([
                    'buyer_id'          => $request->buyer_id,
                    'seller_id'         => $storeData['seller_id'],
                    'order_date'        => now(),
                    'delivery_address'  => $request->delivery_address,
                    'estimated_delivery'=> $request->estimated_delivery,
                    'total_amount'      => $storeData['total_amount'],
                ]);

                foreach ($storeData['items'] as $item) {
                    $product = $item['product'];
                    $product->decrement('stock', $item['quantity']);

                    ProductOrderItem::create([
                        'product_order_id' => $order->product_order_id,
                        'product_id'       => $item['product_id'],
                        'quantity'         => $item['quantity'],
                        'seller_id'        => $item['seller_id'],
                    ]);
                }

                $createdOrders[] = $order->load('items.product.store');
            }

            DB::commit();
            return response()->json(['message' => 'Orders created successfully', 'orders' => $createdOrders], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create orders', 'error' => $e->getMessage()], 500);
        }
    }

    /* ---------------------------------------------------------------------
     | Generic list + show
     |---------------------------------------------------------------------*/
    public function index()
    {
        return response()->json(ProductOrder::with('items.product')->get());
    }

    public function show($id)
    {
        return response()->json(ProductOrder::with('items.product')->findOrFail($id));
    }

    public function getOrdersBySellerId($sellerId)
{
    // Validate sellerId
    if (!is_numeric($sellerId)) {
        return response()->json(['status' => 'error', 'message' => 'Invalid seller ID'], 400);
    }

    $orders = ProductOrder::whereHas('items', fn($q) => $q->where('seller_id', $sellerId))
        ->with([
            'items' => fn($q) => $q->where('seller_id', $sellerId)->with([
                'product' => fn($pq) => $pq->with([
                    'images' => fn($iq) => $iq->where('is_primary', true)->select('product_id', 'image_path'),
                    'store',
                ]),
                'seller',
            ]),
        ])
        ->get();

    return response()->json(['status' => 'success', 'orders' => $orders]);
}

    public function getBuyerOrders(Request $request)
    {
        $user   = $request->user();
        $orders = ProductOrder::where('buyer_id', $user->id)
            ->with([
                'items.product' => fn($q) => $q->with(['images' => fn($iq) => $iq->where('is_primary', true)->select('product_id', 'image_path')]),
                'seller',
            ])->get();
        return response()->json(['status' => 'success', 'orders' => $orders]);
    }

    /* ---------------------------------------------------------------------
     | STATUS CHANGES + Notification hooks
     |---------------------------------------------------------------------*/
    public function update(Request $request, $id)
    {
        $request->validate([
            'order_status'   => 'nullable|in:Pending,Processing,Shipped,Delivered,Canceled',
            'payment_status' => 'nullable|in:Paid,Unpaid',
        ]);

        $order     = ProductOrder::findOrFail($id);
        $oldStatus = $order->order_status;

        if ($request->filled('order_status')) {
            $order->order_status = $request->order_status;
        }
        if ($request->filled('payment_status')) {
            $order->payment_status = $request->payment_status;
        }
        $order->save();

        // Push notification if status changed
        if ($oldStatus !== $order->order_status) {
            $title   = "Order Updated";
            $message = "Your order status for {$order->order_status}.";
            $this->notifyUser($order->buyer_id, $order->product_order_id, $title, $message);
        }

        return response()->json(['message' => 'Order updated successfully', 'order' => $order]);
    }

    public function approveOrder(Request $request, $id)
    {
        $user  = $request->user();
        $order = ProductOrder::with('items.product')->findOrFail($id);

        if ($order->order_status !== 'Pending') {
            return response()->json(['message' => 'Order is not in pending status.'], 403);
        }

        $sellerId = $order->items->first()->seller_id;
        if ($sellerId !== $user->id) {
            return response()->json(['message' => 'You are not authorized to approve this order.'], 403);
        }

        $order->update(['order_status' => 'Processing']);

        // Get product names for notification
        $productNames = $order->items->pluck('product.product_name')->implode(', ');

        // Notify buyer
        $this->notifyUser(
            $order->buyer_id,
            $order->product_order_id,
            'Order Approved',
            "Seller approved your order containing: {$productNames}."
        );

        return response()->json(['message' => 'Order approved successfully.', 'order' => $order]);
    }

    public function markAsShipped(Request $request, $id)
    {
        $user  = $request->user();
        $order = ProductOrder::with('items.product')->findOrFail($id);

        if ($order->order_status !== 'Processing') {
            return response()->json(['message' => 'Order must be in Processing state.'], 403);
        }

        $sellerId = $order->items->first()->seller_id;
        if ($sellerId !== $user->id) {
            return response()->json(['message' => 'You are not authorized to ship this order.'], 403);
        }

        $order->update(['order_status' => 'Shipped']);

        // Get product names for notification
        $productNames = $order->items->pluck('product.product_name')->implode(', ');

        // Notify buyer
        $this->notifyUser(
            $order->buyer_id,
            $order->product_order_id,
            'Order Shipped',
            "Good news! Your order containing: {$productNames} has been shipped."
        );

        return response()->json(['message' => 'Order marked as Shipped', 'order' => $order]);
    }

    public function markAsDelivered(Request $request, $id)
    {
        $user  = $request->user();
        $order = ProductOrder::with('items.product')->findOrFail($id);

        if ($order->order_status !== 'Shipped') {
            return response()->json(['message' => 'Order must be in Shipped state.'], 403);
        }
        if ($order->buyer_id !== $user->id) {
            return response()->json(['message' => 'Only the buyer can mark as Delivered.'], 403);
        }

        $order->update(['order_status' => 'Delivered']);

        // Get product names for notification
        $productNames = $order->items->pluck('product.product_name')->implode(', ');

        // Notify seller
        $sellerId = $order->items->first()->seller_id;
        $this->notifyUser(
            $sellerId,
            $order->product_order_id,
            'Order Delivered',
            "Buyer confirmed delivery of order containing: {$productNames}."
        );

        return response()->json(['message' => 'Order marked as Delivered', 'order' => $order]);
    }

    public function cancelOrder(Request $request, $id)
    {
        $user  = $request->user();
        $order = ProductOrder::with('items.product')->findOrFail($id);

      if ($order->order_status !== 'Pending' && $order->order_status !== 'Shipped') {
    return response()->json(['message' => 'Only Pending or Shipped orders can be canceled.'], 403);
}
        $sellerId = $order->items->first()->seller_id;

        if ($order->buyer_id !== $user->id && $sellerId !== $user->id) {
            return response()->json(['message' => 'You are not authorized to cancel this order.'], 403);
        }

        $order->update(['order_status' => 'Canceled']);

        // Get product names for notification
        $productNames = $order->items->pluck('product.product_name')->implode(', ');

        // Notify both buyer and seller
        $title   = 'Order Canceled';
        $message = "Order containing: {$productNames} has been canceled.";
        $this->notifyUser($order->buyer_id, $order->product_order_id, $title, $message);
        $this->notifyUser($sellerId, $order->product_order_id, $title, $message);

        return response()->json(['message' => 'Order canceled successfully.', 'order' => $order]);
    }

    /* ---------------------------------------------------------------------
     | Destroy
     |---------------------------------------------------------------------*/
    public function destroy($id)
    {
        $order = ProductOrder::findOrFail($id);
        $order->delete();
        return response()->json(['message' => 'Order deleted successfully']);
    }
}