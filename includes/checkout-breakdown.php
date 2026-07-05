<?php
// ============================================================
// CHECKOUT BREAKDOWN UI (reusable partial)
// Renders the "100% to charity" money breakdown: buyer's premium, GoFundMe-style
// tip suggestions, and the honest (pre-checked) processing-cover checkbox. Calls
// /api/checkout/quote.php live so every change re-quotes the exact split.
//
// Phase 3 wires the "Pay" button to the destination-charge endpoint using the
// data-* totals this component publishes on the container.
//
// Usage:  renderCheckoutBreakdown((int)$item['id']);
// ============================================================

function renderCheckoutBreakdown(int $item_id): void {
    ?>
    <div class="sbp-checkout-breakdown" id="sbpCheckout" data-item-id="<?php echo $item_id; ?>"
         data-total-cents="0" data-tip-cents="0" data-cover="1">
        <p class="sbp-guarantee" id="sbpGuarantee">Calculating your 100%-to-charity breakdown…</p>

        <div class="sbp-tip-row" role="group" aria-label="Add an optional tip to support the platform">
            <span class="sbp-tip-label">Add a tip to keep this tool free for nonprofits (optional):</span>
            <div class="sbp-tip-buttons" id="sbpTipButtons"><!-- filled from suggested_tips --></div>
        </div>

        <label class="sbp-cover">
            <input type="checkbox" id="sbpCover" checked>
            <span>Cover processing costs so the charity receives every penny</span>
        </label>

        <dl class="sbp-lines" id="sbpLines">
            <div><dt>Your winning bid</dt><dd id="sbpBid">—</dd></div>
            <div><dt>Platform fee <span id="sbpRate" class="sbp-muted"></span></dt><dd id="sbpPremium">—</dd></div>
            <div class="sbp-tip-line" id="sbpTipLine" hidden><dt>Your tip</dt><dd id="sbpTip">—</dd></div>
            <div class="sbp-cover-line" id="sbpCoverLine" hidden><dt>Processing coverage</dt><dd id="sbpCoverAmt">—</dd></div>
            <div class="sbp-total"><dt>Total you pay</dt><dd id="sbpTotal">—</dd></div>
            <div class="sbp-charity"><dt>Goes to the charity</dt><dd id="sbpToCharity">—</dd></div>
        </dl>
    </div>

    <style>
        .sbp-checkout-breakdown{border:1px solid rgba(23,34,53,.12);border-radius:14px;padding:1.1rem 1.25rem;margin:1rem 0;background:#fff}
        .sbp-guarantee{font-weight:700;color:#28785f;margin:0 0 .9rem}
        .sbp-tip-row{margin:.4rem 0 .8rem}
        .sbp-tip-label{display:block;font-size:.85rem;color:#4a5568;margin-bottom:.45rem}
        .sbp-tip-buttons{display:flex;gap:.5rem;flex-wrap:wrap}
        .sbp-tip-buttons button{border:1px solid rgba(23,34,53,.2);background:#f7fafc;border-radius:999px;padding:.4rem .9rem;font-weight:700;cursor:pointer}
        .sbp-tip-buttons button.is-active{background:#28785f;color:#fff;border-color:#28785f}
        .sbp-cover{display:flex;gap:.55rem;align-items:flex-start;font-size:.9rem;margin:.5rem 0 1rem;cursor:pointer}
        .sbp-lines{margin:0;display:grid;gap:.35rem}
        .sbp-lines>div{display:flex;justify-content:space-between;gap:1rem}
        .sbp-lines dt,.sbp-lines dd{margin:0}
        .sbp-muted{color:#718096;font-weight:400;font-size:.85em}
        .sbp-total{border-top:1px solid rgba(23,34,53,.12);padding-top:.5rem;margin-top:.35rem;font-weight:800}
        .sbp-charity dd{color:#28785f;font-weight:800}
    </style>

    <script>
    (function(){
        var el = document.getElementById('sbpCheckout');
        if (!el) return;
        var itemId = parseInt(el.getAttribute('data-item-id'), 10);
        var tipCents = 0, cover = true;

        function authHeaders(){
            var h = {'Content-Type':'application/json'};
            try { var t = localStorage.getItem('session_token'); if (t) h['Authorization']='Bearer '+t; } catch(e){}
            return h;
        }
        function money(c){ return '$'+(c/100).toFixed(2); }

        function renderTips(tips){
            var wrap = document.getElementById('sbpTipButtons'); wrap.innerHTML='';
            tips.forEach(function(t){
                var b = document.createElement('button'); b.type='button';
                b.textContent = t.percent===0 ? 'No tip' : (t.percent+'% ('+money(t.cents)+')');
                b.setAttribute('data-cents', t.cents);
                if (t.cents===tipCents) b.classList.add('is-active');
                b.addEventListener('click', function(){ tipCents = parseInt(b.getAttribute('data-cents'),10); quote(); });
                wrap.appendChild(b);
            });
        }

        function quote(){
            fetch('/api/checkout/quote.php', {
                method:'POST', headers:authHeaders(), credentials:'include',
                body: JSON.stringify({item_id:itemId, tip_cents:tipCents, cover_processing:cover})
            }).then(function(r){ return r.json(); }).then(function(d){
                if (!d || d.status!=='ok') return;
                document.getElementById('sbpGuarantee').textContent = d.guarantee;
                if (d.suggested_tips) renderTips(d.suggested_tips);
                var b = d.breakdown, disp = d.display;
                document.getElementById('sbpBid').textContent = disp.bid;
                document.getElementById('sbpPremium').textContent = disp.premium;
                document.getElementById('sbpRate').textContent = d.premium_mode==='optional_tip_only' ? '' : '('+disp.premium_rate+')';
                document.getElementById('sbpTotal').textContent = disp.total;
                document.getElementById('sbpToCharity').textContent = disp.to_charity;
                toggle('sbpTipLine','sbpTip', b.tip_cents, disp.tip);
                toggle('sbpCoverLine','sbpCoverAmt', b.processing_cover_cents, disp.cover);
                // Publish totals for the Phase-3 Pay button.
                el.setAttribute('data-total-cents', b.total_cents);
                el.setAttribute('data-tip-cents', b.tip_cents);
                el.setAttribute('data-cover', cover ? '1':'0');
            }).catch(function(){});
        }
        function toggle(lineId, amtId, cents, text){
            var line = document.getElementById(lineId);
            if (cents>0){ document.getElementById(amtId).textContent=text; line.hidden=false; } else { line.hidden=true; }
        }

        document.getElementById('sbpCover').addEventListener('change', function(e){ cover=e.target.checked; quote(); });
        quote();
    })();
    </script>
    <?php
}
