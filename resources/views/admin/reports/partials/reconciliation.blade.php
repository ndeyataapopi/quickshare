<div class="table-responsive">
    <table class="table table-hover table-striped">
        <thead class="thead-light">
            <tr>
                <th>Reference</th>
                <th>Borrower</th>
                <th>Status</th>
                <th class="text-right">Money In</th>
                <th class="text-right">Money Out</th>
                <th class="text-right">Platform Revenue</th>
                <th class="text-center">Reconciled</th>
                <th class="text-center">Discrepancies</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
            <tr class="{{ $row['reconciled'] ? '' : 'table-warning' }}">
                <td>
                    <a href="{{ route('admin.loans.show', $row['loan_id']) }}">{{ $row['reference'] }}</a>
                </td>
                <td>{{ $row['borrower'] }}</td>
                <td>{{ ucwords(str_replace('_', ' ', $row['status'])) }}</td>
                <td class="text-right">N$ {{ number_format($row['money_in'], 2) }}</td>
                <td class="text-right">N$ {{ number_format($row['money_out'], 2) }}</td>
                <td class="text-right">N$ {{ number_format($row['platform_revenue'], 2) }}</td>
                <td class="text-center">
                    @if($row['reconciled'])
                        <span class="badge badge-success">YES</span>
                    @else
                        <span class="badge badge-danger">NO</span>
                    @endif
                </td>
                <td class="text-center">{{ $row['discrepancies'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
