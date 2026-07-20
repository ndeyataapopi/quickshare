<div class="table-responsive">
    <table class="table table-hover table-striped">
        <thead class="thead-light">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Trust Score</th>
                <th>KYC</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $user)
                <tr>
                    <td>{{ $user->first_name }} {{ $user->last_name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->phone }}</td>
                    <td>{{ ucfirst($user->status) }}</td>
                    <td>{{ number_format($user->trust_score, 2) }}</td>
                    <td>{{ ucfirst($user->kycSubmission?->status ?? 'none') }}</td>
                    <td>{{ $user->created_at->format('M j, Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $data->links() }}</div>
