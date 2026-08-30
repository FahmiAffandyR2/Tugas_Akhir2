<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialReportController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date|after_or_equal:from_date',
            'period'    => 'nullable|in:daily,weekly,monthly',
        ]);

        $from = $request->input('from_date', now()->startOfMonth()->toDateString());
        $to   = $request->input('to_date', now()->toDateString());
        $period = $request->input('period', 'daily');

        $revenue = $this->getRevenueSummary($from, $to);
        $byBusType = $this->getRevenueByBusType($from, $to);
        $trend = $this->getRevenueTrend($from, $to, $period);

        return response()->json([
            'summary' => $revenue,
            'by_bus_type' => $byBusType,
            'trend' => $trend,
            'from_date' => $from,
            'to_date' => $to,
        ]);
    }

    public function bookings(Request $request)
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date|after_or_equal:from_date',
            'status'    => 'nullable|string',
        ]);

        $from = $request->input('from_date', now()->startOfMonth()->toDateString());
        $to   = $request->input('to_date', now()->toDateString());

        $bookings = DB::table('charter_bookings as cb')
            ->leftJoin('charter_revenue_transactions as crt', 'crt.charter_booking_id', '=', 'cb.id')
            ->leftJoin('users as u', 'u.id', '=', 'cb.customer_id')
            ->select(
                'cb.id',
                'cb.reference_code',
                'u.name as customer_name',
                'cb.origin',
                'cb.destination',
                'cb.bus_type',
                'cb.passenger_count',
                'cb.quoted_price',
                'cb.status',
                'crt.amount',
                'crt.status as payment_status',
                'crt.paid_at',
                'cb.created_at'
            )
            ->whereBetween('cb.created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);

        if ($request->filled('status')) {
            $bookings->where('cb.status', $request->status);
        }

        $results = $bookings->orderBy('cb.created_at', 'desc')->paginate(50);

        return response()->json($results);
    }

    private function getRevenueSummary($from, $to)
    {
        $result = DB::table('charter_revenue_transactions')
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_revenue')
            ->selectRaw('COALESCE(AVG(amount), 0) as avg_transaction')
            ->selectRaw('SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as paid_count')
            ->selectRaw('SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END) as paid_amount')
            ->selectRaw('SUM(CASE WHEN status != "paid" THEN 1 ELSE 0 END) as unpaid_count')
            ->selectRaw('SUM(CASE WHEN status != "paid" THEN amount ELSE 0 END) as unpaid_amount')
            ->whereBetween('paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->first();

        $bookings = DB::table('charter_bookings')
            ->selectRaw('COUNT(*) as total_bookings')
            ->selectRaw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            ->selectRaw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled')
            ->selectRaw('SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as confirmed')
            ->selectRaw('SUM(quoted_price) as total_quoted')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->first();

        return [
            'total_transactions' => $result->total_transactions,
            'total_revenue' => (float) $result->total_revenue,
            'avg_transaction' => (float) $result->avg_transaction,
            'paid_count' => (int) $result->paid_count,
            'paid_amount' => (float) $result->paid_amount,
            'unpaid_count' => (int) $result->unpaid_count,
            'unpaid_amount' => (float) $result->unpaid_amount,
            'total_bookings' => $bookings->total_bookings,
            'completed_bookings' => $bookings->completed,
            'cancelled_bookings' => $bookings->cancelled,
            'confirmed_bookings' => $bookings->confirmed,
            'total_quoted' => (float) $bookings->total_quoted,
        ];
    }

    private function getRevenueByBusType($from, $to)
    {
        return DB::table('charter_revenue_transactions as crt')
            ->join('charter_bookings as cb', 'cb.id', '=', 'crt.charter_booking_id')
            ->select('cb.bus_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(crt.amount) as total'))
            ->whereBetween('crt.paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->groupBy('cb.bus_type')
            ->orderByDesc('total')
            ->get();
    }

    private function getRevenueTrend($from, $to, $period)
    {
        $dateFormat = match ($period) {
            'weekly' => '%x-W%u',
            'monthly' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        return DB::table('charter_revenue_transactions')
            ->selectRaw("DATE_FORMAT(paid_at, '{$dateFormat}') as period")
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(amount) as total')
            ->whereBetween('paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }
}
