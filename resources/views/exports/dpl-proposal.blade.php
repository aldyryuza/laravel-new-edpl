{{-- Export Excel DPL (App\Exports\DplProposalExport), format mengikuti legacy printout_page_excel. --}}
<table>
    <tr>
        <th colspan="4"><strong>Discount Proposal List (DPL)</strong></th>
    </tr>
    <tr>
        <th>DPL No.</th>
        <td colspan="3">{{ $proposal->dpl_number }}</td>
    </tr>
    <tr>
        <th>Period Date</th>
        <td colspan="3">{{ $proposal->date_start->format('d-m-Y') }} s/d {{ $proposal->date_end->format('d-m-Y') }}</td>
    </tr>
    <tr>
        <th>Ship to</th>
        <td colspan="3">{{ $proposal->outlet?->code }} - {{ $proposal->outlet?->name }}</td>
    </tr>
    <tr>
        <th>Address</th>
        <td colspan="3">{{ $proposal->outlet?->address }}</td>
    </tr>
    <tr>
        <th>Team</th>
        <td colspan="3">{{ $proposal->team_code }}</td>
    </tr>
    <tr>
        <th>Jenis</th>
        <td colspan="3">{{ $proposal->proposal_type }}</td>
    </tr>
    <tr>
        <th>Kontrak</th>
        <td colspan="3">{{ $proposal->is_contract ? 'Ya' : 'Tidak' }}</td>
    </tr>
    <tr>
        <th>Bed / 25% Bed</th>
        <td colspan="3">{{ $proposal->bed_count_snapshot ?? '-' }} / {{ $proposal->bed_order_minimum ?? '-' }}</td>
    </tr>
    <tr>
        <th>Tanggal Penawaran</th>
        <td colspan="3">{{ $proposal->date_offer->format('d-m-Y') }}</td>
    </tr>
    <tr>
        <th>Pengaju</th>
        <td colspan="3">{{ $proposal->creator?->name }} ({{ $proposal->creator?->nik }})</td>
    </tr>
    <tr>
        <th>Status</th>
        <td colspan="3">{{ $allApproved ? 'APPROVED' : $proposal->proposal_status }}</td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th colspan="21"><strong>DPL ITEMS</strong></th>
        </tr>
        <tr>
            <th rowspan="3">No.</th>
            <th rowspan="3">Distributor</th>
            <th rowspan="3">Produk</th>
            <th rowspan="3">Min. Order / Bulan</th>
            <th rowspan="3">Program Bonus Barang</th>
            <th rowspan="3">Program Disc Total %</th>
            <th colspan="4">Discount Share %</th>
            <th rowspan="3">Direct</th>
            <th rowspan="3">Ambil Dari Pabrik</th>
            <th rowspan="3">Support Off for SAI</th>
            <th rowspan="3">Support Logistic Satoria (%)</th>
            <th rowspan="3">Logistic Fee (%)</th>
            <th rowspan="3">Calculator %</th>
            <th rowspan="3">HNA+PPN-DISCOUNT(ON)</th>
            <th rowspan="3">HNA-DISCOUNT(ON)</th>
            <th rowspan="3">Price Type</th>
            <th rowspan="3">Margin (%)</th>
            <th rowspan="3">Remark</th>
        </tr>
        <tr>
            <th colspan="2">Distributor</th>
            <th colspan="2">Principal</th>
        </tr>
        <tr>
            <th>On Faktur</th>
            <th>Off Faktur</th>
            <th>On Faktur</th>
            <th>Off Faktur</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item['no'] }}</td>
                <td>{{ $item['distributor'] }}</td>
                <td>{{ $item['product'] }}</td>
                <td>{{ $item['minimum_order'] }}</td>
                <td>{{ $item['bonus'] }}</td>
                <td>{{ $item['discount_total'] }}</td>
                <td>{{ $item['distributor_on'] }}</td>
                <td>{{ $item['distributor_off'] }}</td>
                <td>{{ $item['principle_on'] }}</td>
                <td>{{ $item['principle_off'] }}</td>
                <td>{{ $item['direct'] }}</td>
                <td>{{ $item['factory_pickup'] }}</td>
                <td>{{ $item['support'] }}</td>
                <td>{{ $item['support_logistic'] }}</td>
                <td>{{ $item['logistic_fee'] }}</td>
                <td>{{ $item['calculator'] }}</td>
                <td>{{ $item['hna_ppn_disc'] }}</td>
                <td>{{ $item['hna_disc'] }}</td>
                <td>{{ $item['price_type'] }}</td>
                <td>{{ $item['margin'] }}</td>
                <td>{{ $item['remark'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th colspan="4"><strong>STATUS APPROVAL</strong></th>
        </tr>
        <tr>
            <th>Role</th>
            <th>Approval Status</th>
            <th>Approver</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($steps as $step)
            <tr>
                <td>{{ $step['role'] }}</td>
                <td>{{ $step['status'] }}</td>
                <td>{{ $step['approvers'] }}</td>
                <td>{{ $step['date'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
