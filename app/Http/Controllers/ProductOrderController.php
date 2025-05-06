<?php

namespace App\Http\Controllers;

use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use Illuminate\Http\Request;
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
            'user_id' => 'required|exists:users,id',
            'delivery_address' => 'required|string',
            'estimated_delivery' => 'nullable|date',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,product_id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // Create the order
            $order = ProductOrder::create([
                'user_id' => $request->user_id,
                'order_date' => now(),
                'delivery_address' => $request->delivery_address,
                'estimated_delivery' => $request->estimated_delivery,
            ]);

            // Create the order items
            foreach ($request->items as $item) {
                ProductOrderItem::create([
                    'product_order_id' => $order->product_order_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Order created successfully', 'order' => $order], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create order', 'error' => $e->getMessage()], 500);
        }
    }

    // Show a single order
    public function show($id)
    {
        $order = ProductOrder::with('items.product')->findOrFail($id);
        return response()->json($order);
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
