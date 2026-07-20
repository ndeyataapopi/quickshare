<div class="table-responsive">
    <table class="table table-hover table-striped">
        <thead class="thead-light">
            <tr>
                <th>Reference</th>
                <th>Borrower</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Interest</th>
                <th>Term</th>
                <th>Total Repayment</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $loan)
                <tr>
                    <td>{{ $loan->reference }}</td>
                    <td>{{ optional($loan->borrower)->first_name }} {{ optional($loan->borrower)->last_name }}</td>
                    <td>{{ kpiMoney($loan->requested_amount) }}</td>
                    <td>{{ ucfirst($loan->status) }}</td>
                    <td>{{ number_format($loan->interest_rate, 2) }}%</td>
                    <td>{{ $loan->loan_term_days }} days</td>
                    <td>{{ kpiMoney($loan->total_repayment) }}</td>
                    <td>{{ $loan->created_at->format('M j, Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $data->links() }}</div>
