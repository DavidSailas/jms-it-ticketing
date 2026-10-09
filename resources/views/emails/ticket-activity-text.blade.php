{{ $title }}

Hi {{ $name }},

{{ $lead }}
@if ($ticket)

Ticket:    {{ $ticket->ticket_no }}
Subject:   {{ $ticket->subject }}
Status:    {{ $ticket->statusLabel() }}
Priority:  {{ $ticket->priorityLabel() }}
@if ($ticket->assignee)
Engineer:  {{ $ticket->assignee->name }}
@endif
@endif

{{ $button }}: {{ $url }}

--
{{ $brandName }} on JMS One IT
@if ($alwaysSent)
Security emails are always sent.
@else
Change your email preferences: {{ $prefsUrl }}
@endif
