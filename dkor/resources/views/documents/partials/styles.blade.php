{{-- Styles communs des documents imprimables et envoyés par courriel. --}}
<style>
    @page { size: letter; margin: 0.5in; }
    body { margin: 0; padding: 24px; background: #fff; color: #18181b; font-family: "Helvetica Neue", Arial, sans-serif; font-size: 13px; line-height: 1.45; }
    .document { max-width: 800px; margin: 0 auto; }
    .toolbar { max-width: 800px; margin: 0 auto 16px; text-align: right; }
    .toolbar button { padding: 8px 16px; border: 1px solid #d4d4d8; border-radius: 6px; background: #18181b; color: #fff; font-size: 13px; cursor: pointer; }
    table { width: 100%; border-collapse: collapse; }
    .header td { vertical-align: top; }
    .issuer { font-size: 12px; color: #52525b; }
    .issuer .party-name { font-size: 18px; font-weight: 700; color: #18181b; }
    .title { text-align: right; }
    .title h1 { margin: 0 0 6px; font-size: 22px; letter-spacing: 0.04em; }
    .meta td { padding: 1px 0 1px 12px; text-align: right; }
    .meta .label { color: #71717a; }
    .parties { margin-top: 24px; }
    .parties td { width: 33.33%; vertical-align: top; padding: 10px 12px; border: 1px solid #e4e4e7; }
    .parties h2 { margin: 0 0 4px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #71717a; }
    .party-name { font-weight: 600; }
    .lines { margin-top: 24px; }
    .lines th { padding: 8px; border-bottom: 2px solid #18181b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; text-align: left; }
    .lines td { padding: 8px; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
    .lines .num { text-align: right; white-space: nowrap; }
    .lines tfoot td { border-bottom: 0; font-weight: 700; font-size: 14px; }
    .totals { width: 320px; margin: 16px 0 0 auto; }
    .totals td { padding: 3px 8px; }
    .totals .num { text-align: right; white-space: nowrap; }
    .totals .muted { color: #71717a; font-size: 11px; }
    .totals .grand td { border-top: 2px solid #18181b; font-weight: 700; font-size: 14px; padding-top: 6px; }
    .line-detail { font-size: 11px; color: #71717a; }
    .notes { margin-top: 20px; padding: 10px 12px; background: #f4f4f5; white-space: pre-line; }
    .footer { margin-top: 28px; font-size: 12px; color: #52525b; }
    @media print {
        body { padding: 0; }
        .toolbar { display: none; }
        .lines tr { break-inside: avoid; }
    }
</style>
