<td>{{ $r['doc_no'] }}</td>
<td>{{ $r['rev_no'] }}</td>
<td title="{{ $r['title'] }}">{{ $r['title'] }}</td>
<td>{{ $r['effectivity'] }}</td>
<td>{{ $r['originator'] }}</td>
<td style="text-align:center">{{ $r['pages'] }}</td>
<td style="text-align:center"><span class="db-status">{{ $r['status'] }}</span></td>
<td style="text-align:center">
    @if($r['pdf_path'])
        <a href="{{ $r['pdf_path'] }}" class="db-pdf-link" target="_blank" rel="noopener">PDF</a>
    @else
        <span class="db-na">—</span>
    @endif
</td>
<td class="db-source-unit">{{ $r['source_unit'] }}</td>
<td>
    @forelse($r['related'] ?? [] as $rel)
        <span class="db-related-tag">{{ $rel['title'] ?? $rel['doc_no'] }}</span>
    @empty
        <span class="db-na">—</span>
    @endforelse
</td>

<td class="col-group-summary-body col-group-summary-approval" x-show="!open.approval">
    @if($r['approval_no'] || $r['approval_date'])<span class="db-summary-check">✓</span>@else<span class="db-summary-x">✗</span>@endif
</td>
<td class="col-group-approval" x-show="open.approval">{{ $r['approval_no'] ?: '—' }}</td>
<td class="col-group-approval" x-show="open.approval">{{ $r['approval_date'] ?: '—' }}</td>

<td class="col-group-summary-body col-group-summary-deadline" x-show="!open.deadline">
    @if($r['deadline_date'] || $r['deadline_diff'])<span class="db-summary-check">✓</span>@else<span class="db-summary-x">✗</span>@endif
</td>
<td class="col-group-deadline" x-show="open.deadline">{{ $r['deadline_date'] ?: '—' }}</td>
<td class="col-group-deadline" x-show="open.deadline">{{ $r['deadline_diff'] ?: '—' }}</td>

<td class="col-group-summary-body col-group-summary-masterlist" x-show="!open.masterlist">
    @if($r['ml_receipt_date'] || $r['ml_receipt_time'] || $r['ml_register_date'] || $r['ml_register_time'])<span class="db-summary-check">✓</span>@else<span class="db-summary-x">✗</span>@endif
</td>
<td class="col-group-masterlist" x-show="open.masterlist">{{ $r['ml_receipt_date'] ?: '—' }}</td>
<td class="col-group-masterlist" x-show="open.masterlist">{{ $r['ml_receipt_time'] ?: '—' }}</td>
<td class="col-group-masterlist" x-show="open.masterlist">{{ $r['ml_register_date'] ?: '—' }}</td>
<td class="col-group-masterlist" x-show="open.masterlist">{{ $r['ml_register_time'] ?: '—' }}</td>

<td class="col-group-summary-body col-group-summary-dcn" x-show="!open.dcn">
    @if($r['dcn_no'] || $r['dcn_date'] || $r['dcn_receipt_date'] || $r['dcn_receipt_time'] || $r['dcn_purpose'] || $r['dcn_scan'])<span class="db-summary-check">✓</span>@else<span class="db-summary-x">✗</span>@endif
</td>
<td class="col-group-dcn" x-show="open.dcn">{{ $r['dcn_no'] ?: '—' }}</td>
<td class="col-group-dcn" x-show="open.dcn">{{ $r['dcn_date'] ?: '—' }}</td>
<td class="col-group-dcn" x-show="open.dcn">{{ $r['dcn_receipt_date'] ?: '—' }}</td>
<td class="col-group-dcn" x-show="open.dcn">{{ $r['dcn_receipt_time'] ?: '—' }}</td>
<td class="col-group-dcn" x-show="open.dcn">{{ $r['dcn_purpose'] ?: '—' }}</td>
<td class="col-group-dcn" x-show="open.dcn">@if($r['dcn_scan'])<a href="{{ $r['dcn_scan'] }}" target="_blank">PDF</a>@else—@endif</td>

<td class="col-group-summary-body col-group-summary-drf" x-show="!open.drf">
    @if($r['drf_no'] || $r['drf_date'] || $r['drf_receipt_date'] || $r['drf_receipt_time'] || $r['drf_scan'])<span class="db-summary-check">✓</span>@else<span class="db-summary-x">✗</span>@endif
</td>
<td class="col-group-drf" x-show="open.drf">{{ $r['drf_no'] ?: '—' }}</td>
<td class="col-group-drf" x-show="open.drf">{{ $r['drf_date'] ?: '—' }}</td>
<td class="col-group-drf" x-show="open.drf">{{ $r['drf_receipt_date'] ?: '—' }}</td>
<td class="col-group-drf" x-show="open.drf">{{ $r['drf_receipt_time'] ?: '—' }}</td>
<td class="col-group-drf" x-show="open.drf">@if($r['drf_scan'])<a href="{{ $r['drf_scan'] }}" target="_blank">PDF</a>@else—@endif</td>

<td class="col-group-summary-body col-group-summary-distribution" x-show="!open.distribution">
    @if($r['dist_onfile_date'] || $r['dist_onfile_time'] || $r['dist_actual_date'] || $r['dist_actual_time'] || $r['dist_offices'] || $r['dist_scan'])<span class="db-summary-check">✓</span>@else<span class="db-summary-x">✗</span>@endif
</td>
<td class="col-group-distribution" x-show="open.distribution">{{ $r['dist_onfile_date'] ?: '—' }}</td>
<td class="col-group-distribution" x-show="open.distribution">{{ $r['dist_onfile_time'] ?: '—' }}</td>
<td class="col-group-distribution" x-show="open.distribution">{{ $r['dist_actual_date'] ?: '—' }}</td>
<td class="col-group-distribution" x-show="open.distribution">{{ $r['dist_actual_time'] ?: '—' }}</td>
<td class="col-group-distribution" x-show="open.distribution">{{ $r['dist_offices'] ?: '—' }}</td>
<td class="col-group-distribution" x-show="open.distribution">@if($r['dist_scan'])<a href="{{ $r['dist_scan'] }}" target="_blank">PDF</a>@else—@endif</td>

<td class="col-group-summary-body col-group-summary-retrieval" x-show="!open.retrieval">
    @if($r['ret_onfile'] || $r['ret_actual'] || $r['ret_offices'] || $r['ret_scan'])<span class="db-summary-check">✓</span>@else<span class="db-summary-x">✗</span>@endif
</td>
<td class="col-group-retrieval" x-show="open.retrieval">{{ $r['ret_onfile'] ?: '—' }}</td>
<td class="col-group-retrieval" x-show="open.retrieval">{{ $r['ret_actual'] ?: '—' }}</td>
<td class="col-group-retrieval" x-show="open.retrieval">{{ $r['ret_offices'] ?: '—' }}</td>
<td class="col-group-retrieval" x-show="open.retrieval">@if($r['ret_scan'])<a href="{{ $r['ret_scan'] }}" target="_blank">PDF</a>@else—@endif</td>
