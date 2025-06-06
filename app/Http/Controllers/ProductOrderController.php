<?php
namespace App\Http\Controllers;

use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductOrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'buyer_id' => 'required|exists:users,id',
            'delivery_address' => 'required|string',
            'estimated_delivery' => 'nullable|date',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,product_id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // Group items by store_id (and thus by seller_id via store.owner_id)
            $itemsByStore = [];
            foreach ($request->items as $item) {
                $product = Product::with('store')->findOrFail($item['product_id']);
                if (!$product->store || !$product->store->owner_id) {
                    throw new \Exception("Seller information could not be retrieved for product: {$product->product_name}");
                }
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->product_name}");
                }

                $storeId = $product->store_id;
                $sellerId = $product->store->owner_id; // Get seller_id from store.owner_id

                if (!isset($itemsByStore[$storeId])) {
                    $itemsByStore[$storeId] = [
                        'seller_id' => $sellerId,
                        'total_amount' => 0,
                        'items' => [],
                    ];
                }

                $itemsByStore[$storeId]['total_amount'] += $product->price * $item['quantity'];
                $itemsByStore[$storeId]['items'][] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'seller_id' => $sellerId, // Store seller_id for the item
                    'product' => $product,
                ];
            }

            $createdOrders = [];

            // Create a separate order for each store/seller
            foreach ($itemsByStore as $storeId => $storeData) {
                // Create the order for this seller
                $order = ProductOrder::create([
                    'buyer_id' => $request->buyer_id,
                    'seller_id' => $storeData['seller_id'], // Use store.owner_id as seller_id
                    'order_date' => now(),
                    'delivery_address' => $request->delivery_address,
                    'estimated_delivery' => $request->estimated_delivery,
                    'total_amount' => $storeData['total_amount'],
                ]);

                // Process order items
                foreach ($storeData['items'] as $item) {
                    $product = $item['product'];
                    $product->stock -= $item['quantity'];
                    $product->save();

                    ProductOrderItem::create([
                        'product_order_id' => $order->product_order_id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'seller_id' => $item['seller_id'], // Include seller_id for the item
                    ]);
                }

                $createdOrders[] = $order->load('items.product.store');
            }

            DB::commit();

            return response()->json([
                'message' => 'Orders created successfully',
                'orders' => $createdOrders,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // Other methods (index, show, etc.) remain unchanged
    public function index()
    {
        $orders = ProductOrder::with('items.product')->get();
        return response()->json($orders);
    }

    public function show($id)
    {
        $order = ProductOrder::with('items.product')->findOrFail($id);
        return response()->json($order);
    }

   public function getOrdersBySellerId($sellerId)
    {
        $orders = ProductOrder::whereHas('items', function ($query) use ($sellerId) {
            $query->where('seller_id', $sellerId);
        })->with(['items.product.store'])->get();
        
        return response()->json($orders);
    }

    public function approveOrder(Request $request, $id)
    {
        $user = auth()->user();
        $order = ProductOrder::with('items.product.store')->findOrFail($id);

        // Ensure order is still pending
        if ($order->order_status !== 'Pending') {
            return response()->json(['message' => 'Order is not in pending status.'], 403);
        }

        // Check if the order has items
        if ($order->items->isEmpty()) {
            return response()->json(['message' => 'Order has no items to approve.'], 400);
        }

        // Get the seller_id from the first item and validate all items have the same seller_id
        $sellerId = $order->items->first()->seller_id;
        $allItemsMatchSeller = $order->items->every(function ($item) use ($sellerId) {
            return $item->seller_id === $sellerId;
        });

        if (!$allItemsMatchSeller) {
            return response()->json(['message' => 'Order contains items from multiple sellers.'], 403);
        }

        // Verify the authenticated user is the seller
        if ($sellerId !== $user->id) {
            return response()->json(['message' => 'You are not authorized to approve this order.'], 403);
        }

        $order->order_status = 'Processing';
        $order->save();

        return response()->json([
            'message' => 'Order approved successfully.',
            'order' => $order,
            'seller_id' => $sellerId,
        ]);
    }

    public function getBuyerOrders(Request $request)
{
    $user = $request->user();
    $orders = ProductOrder::where('buyer_id', $user->id)
        ->with([
            'items.product' => function ($query) {
                $query->with(['images' => function ($imageQuery) {
                    $imageQuery->where('is_primary', true)->select('product_id', 'image_path');
                }]);
            },
            'seller'
        ])
        ->get();

    return response()->json([
        'status' => 'success',
        'orders' => $orders
    ]);
}

    public function update(Request $request, $id)
    {
        $request->validate([
            'order_status' => 'nullable|in:Pending,Processing,Shipped,Delivered,Canceled',
            'payment_status' => 'nullable|in:Paid,Unpaid',
        ]);

        $order = ProductOrder::findOrFail($id);

        if ($request->has('order_status')) {
            $order->order_status = $request->order_status;
        }

        if ($request->has('payment_status')) {
            $order->payment_status = $request->payment_status;
        }

        $order->save();

        return response()->json(['message' => 'Order updated successfully', 'order' => $order]);
    }

    public function destroy($id)
    {
        $order = ProductOrder::findOrFail($id);
        $order->delete();

        return response()->json(['message' => 'Order deleted successfully']);
    }
    // Seller marks order as Shipped
public function markAsShipped(Request $request, $id)
{
    $user = $request->user();
    $order = ProductOrder::with('items')->findOrFail($id);

    if ($order->order_status !== 'Processing') {
        return response()->json(['message' => 'Order must be in Processing state.'], 403);
    }

    $sellerId = $order->items->first()->seller_id;
    if ($sellerId !== $user->id) {
        return response()->json(['message' => 'You are not authorized to ship this order.'], 403);
    }

    $order->order_status = 'Shipped';
    $order->save();

    return response()->json(['message' => 'Order marked as Shipped', 'order' => $order]);
}

// Buyer marks order as Delivered
public function markAsDelivered(Request $request, $id)
{
    $user = $request->user();
    $order = ProductOrder::findOrFail($id);

    if ($order->order_status !== 'Shipped') {
        return response()->json(['message' => 'Order must be in Shipped state.'], 403);
    }

    if ($order->buyer_id !== $user->id) {
        return response()->json(['message' => 'Only the buyer can mark the order as Delivered.'], 403);
    }

    $order->order_status = 'Delivered';
    $order->save();

    return response()->json(['message' => 'Order marked as Delivered', 'order' => $order]);
}

// Seller or Buyer cancels order if still Pending
public function cancelOrder(Request $request, $id)
{
    $user = $request->user();
    $order = ProductOrder::with('items')->findOrFail($id);

    if ($order->order_status !== 'Pending') {
        return response()->json(['message' => 'Only Pending orders can be canceled.'], 403);
    }

    $sellerId = $order->items->first()->seller_id;
    if ($order->buyer_id !== $user->id && $sellerId !== $user->id) {
        return response()->json(['message' => 'You are not authorized to cancel this order.'], 403);
    }

    $order->order_status = 'Canceled';
    $order->save();

    return response()->json(['message' => 'Order canceled successfully.', 'order' => $order]);
}

}