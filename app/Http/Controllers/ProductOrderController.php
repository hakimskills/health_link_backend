<?php

namespace App\Http\Controllers;

use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use Illuminate\Http\Request;
use App\Models\Product;

use Illuminate\Support\Facades\DB;

class ProductOrderController extends Controller
{
    // List all orders
    public function index()
    {
        $orders = ProductOrder::with('items.product')->get();
        return response()->json($orders);
    }

    // Create a new order
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
        // Get seller from the first product
        $firstProduct = Product::with('store')->findOrFail($request->items[0]['product_id']);
        if (!$firstProduct->store || !$firstProduct->store->owner_id) {
            throw new \Exception("Seller information could not be retrieved from the product's store.");
        }
        $seller_id = $firstProduct->store->owner_id;

        $totalAmount = 0;

        // Calculate total before creating order
        foreach ($request->items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $totalAmount += $product->price * $item['quantity'];
        }

        // Create the order
        $order = ProductOrder::create([
            'buyer_id' => $request->buyer_id,
            'seller_id' => $seller_id,
            'order_date' => now(),
            'delivery_address' => $request->delivery_address,
            'estimated_delivery' => $request->estimated_delivery,
            'total_amount' => $totalAmount, // 💰 Save total
        ]);

        // Process order items
        foreach ($request->items as $item) {
            $product = Product::findOrFail($item['product_id']);

            if ($product->stock < $item['quantity']) {
                throw new \Exception("Insufficient stock for product: {$product->product_name}");
            }

            $product->stock -= $item['quantity'];
            $product->save();

            ProductOrderItem::create([
                'product_order_id' => $order->product_order_id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        DB::commit();

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to create order',
            'error' => $e->getMessage()
        ], 500);
    }
}




    // Show a single order
    public function show($id)
    {
        $order = ProductOrder::with('items.product')->findOrFail($id);
        return response()->json($order);
    }
    public function getOrdersBySellerId($sellerId)
{
    // Get orders where the seller_id matches the owner of the product's store
    $orders = ProductOrder::whereHas('items.product.store', function ($query) use ($sellerId) {
        $query->where('owner_id', $sellerId);
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

    // Ensure the authenticated user is the owner (seller) of **all** products
    $isOwner = collect($order->items)->every(function ($item) use ($user) {
        return $item->product->store->owner_id === $user->id;
    });

    if (!$isOwner) {
        return response()->json(['message' => 'You are not authorized to approve this order.'], 403);
    }

    $order->order_status = 'Processing';
    $order->save();

    return response()->json(['message' => 'Order approved successfully.', 'order' => $order]);
}
public function getBuyerOrders(Request $request)
{
    $user = $request->user(); // authenticated user

    $orders = ProductOrder::where('buyer_id', $user->id)
        ->with(['items.product', 'seller']) // eager load items → product and seller
        ->get();

    return response()->json([
        'status' => 'success',
        'orders' => $orders
    ]);
}



    

    // Update an order (status or payment status)
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

    // Delete an order
    public function destroy($id)
    {
        $order = ProductOrder::findOrFail($id);
        $order->delete();

        return response()->json(['message' => 'Order deleted successfully']);
    }
}
