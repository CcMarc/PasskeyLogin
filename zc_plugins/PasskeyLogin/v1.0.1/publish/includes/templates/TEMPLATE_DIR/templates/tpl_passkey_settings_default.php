<?php
/**
 * Module: PasskeyLogin
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 8.0+ with OpenSSL
 * @author      Marcopolo
 * @copyright   2026
 * @license     GNU General Public License (GPL) - https://www.zen-cart.com/license/2_0.txt
 * @version     1.0.0
 * @updated     08-23-2026
 * @github      https://github.com/CcMarc/PasskeyLogin
 */
// Template for the passkey_settings page. PUBLISHED into the active
// template's templates/ directory by the installer: deploy changes by
// reinstalling, a bare zc_plugins swap does not update this file.
//
// All interactive UI is self-drawn with inline styles, so nothing relies
// on theme button classes or native radio/checkbox styling (which many
// templates hide or restyle). The remove confirmation is a drawn-in-place
// confirm row, not a native confirm dialog.
?>
<div class="centerColumn" id="passkeySettings" style="max-width:860px;margin:0 auto;">

<h1 id="passkeySettingsHeading" style="margin-bottom:6px;"><?php echo HEADING_TITLE; ?></h1>

<?php if ($messageStack->size('passkey_settings') > 0) echo $messageStack->output('passkey_settings'); ?>

<?php if (!$pkl_available) { ?>
    <p><?php echo PKL_PAGE_UNAVAILABLE; ?></p>
    <p><a href="<?php echo zen_href_link(FILENAME_ACCOUNT, '', 'SSL'); ?>"><?php echo PKL_BACK_TO_ACCOUNT; ?></a></p>
<?php } else { ?>

<p style="margin:0 0 18px;opacity:0.85;"><?php echo PKL_PAGE_INTRO; ?></p>

<div id="pklStatus" style="display:none;margin:0 0 16px;padding:12px 14px;border:1px solid #d8d2c4;border-radius:8px;background:#faf8f3;font-size:0.95em;"></div>

<?php if (count($pkl_passkeys) === 0) { ?>
    <p id="pklEmpty" style="margin:0 0 18px;"><?php echo PKL_PAGE_NONE_YET; ?></p>
<?php } else { ?>
    <div style="margin:0 0 18px;">
    <?php foreach ($pkl_passkeys as $pk) { ?>
        <div class="pkl-row" style="border:1px solid #ddd;border-radius:10px;padding:14px 16px;margin:0 0 12px;display:flex;flex-wrap:wrap;align-items:center;gap:12px;">
            <span aria-hidden="true" style="font-size:1.5em;line-height:1;"><i class="fa-solid fa-fingerprint"></i></span>
            <div style="flex:1 1 240px;min-width:200px;">
                <form method="post" action="index.php?main_page=passkey_settings&amp;action=rename" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0;">
                    <input type="hidden" name="securityToken" value="<?php echo htmlspecialchars($pkl_token, ENT_QUOTES); ?>">
                    <input type="hidden" name="passkey_id" value="<?php echo (int)$pk['passkey_id']; ?>">
                    <input type="text" name="device_label" maxlength="80" value="<?php echo htmlspecialchars($pk['device_label'], ENT_QUOTES); ?>" style="flex:1 1 160px;min-width:140px;padding:7px 10px;border:1px solid #ccc;border-radius:6px;font-size:0.95em;">
                    <button type="submit" style="padding:7px 14px;background:#fff;border:1px solid #2f2a22;color:#2f2a22;border-radius:6px;font-size:0.9em;cursor:pointer;white-space:nowrap;"><?php echo PKL_BUTTON_SAVE_NAME; ?></button>
                </form>
                <div style="font-size:0.85em;opacity:0.7;margin-top:5px;">
                    <?php echo PKL_LABEL_ADDED . ' ' . zen_date_short($pk['date_added']); ?>
                    <?php if (!empty($pk['last_used'])) echo ' &nbsp;|&nbsp; ' . PKL_LABEL_LAST_USED . ' ' . zen_date_short($pk['last_used']); ?>
                </div>
            </div>
            <div class="pkl-actions" style="white-space:nowrap;">
                <a href="#" class="pkl-remove-open" style="color:#8a2f2f;text-decoration:underline;font-size:0.92em;cursor:pointer;"><?php echo PKL_BUTTON_REMOVE; ?></a>
            </div>
            <div class="pkl-confirm" style="display:none;flex-basis:100%;padding:10px 12px;border:1px solid #e2c9c9;border-radius:8px;background:#fbf4f4;align-items:center;gap:10px;flex-wrap:wrap;">
                <span style="font-size:0.95em;"><?php echo PKL_CONFIRM_REMOVE; ?></span>
                <form method="post" action="index.php?main_page=passkey_settings&amp;action=remove" style="display:inline;margin:0;">
                    <input type="hidden" name="securityToken" value="<?php echo htmlspecialchars($pkl_token, ENT_QUOTES); ?>">
                    <input type="hidden" name="passkey_id" value="<?php echo (int)$pk['passkey_id']; ?>">
                    <button type="submit" style="padding:8px 14px;background:#8a2f2f;border:1px solid #8a2f2f;color:#fff;border-radius:6px;font-size:0.9em;cursor:pointer;"><?php echo PKL_BUTTON_REMOVE_YES; ?></button>
                </form>
                <a href="#" class="pkl-remove-cancel" style="color:#6b6455;text-decoration:underline;font-size:0.92em;cursor:pointer;"><?php echo PKL_BUTTON_CANCEL; ?></a>
            </div>
        </div>
    <?php } ?>
    </div>
<?php } ?>

<?php if ($pkl_max_reached) { ?>
    <p style="margin:0 0 18px;opacity:0.85;"><?php echo PKL_PAGE_MAX_REACHED; ?></p>
<?php } else { ?>
    <button type="button" id="pklAddBtn" style="display:inline-block;padding:12px 22px;background:#2f2a22;color:#fff;border:1px solid #2f2a22;border-radius:6px;font-weight:600;font-size:1em;cursor:pointer;">
        <?php echo PKL_BUTTON_ADD; ?>
    </button>
<?php } ?>

<p style="margin:22px 0 0;"><a href="<?php echo zen_href_link(FILENAME_ACCOUNT, '', 'SSL'); ?>"><?php echo PKL_BACK_TO_ACCOUNT; ?></a></p>

<script>
(function(){
    var tok = <?php echo json_encode($pkl_token); ?>;
    var oUrl = 'index.php?main_page=passkey_settings&action=ajax_reg_options';
    var vUrl = 'index.php?main_page=passkey_settings&action=ajax_reg_verify';
    var L = {
        unsupported: <?php echo json_encode(PKL_JS_UNSUPPORTED); ?>,
        generic: <?php echo json_encode(PKL_JS_GENERIC); ?>,
        cancelled: <?php echo json_encode(PKL_JS_CANCELLED); ?>,
        already: <?php echo json_encode(PKL_JS_ALREADY); ?>,
        working: <?php echo json_encode(PKL_JS_WORKING); ?>
    };

    function pklToBuf(o){var p='=?BINARY?B?',s='?=';if(typeof o==='object'&&o!==null){for(var k in o){if(typeof o[k]==='string'){var v=o[k];if(v.substring(0,p.length)===p&&v.substring(v.length-s.length)===s){v=v.substring(p.length,v.length-s.length);var bs=window.atob(v);var by=new Uint8Array(bs.length);for(var i=0;i<bs.length;i++){by[i]=bs.charCodeAt(i);}o[k]=by.buffer;}}else{pklToBuf(o[k]);}}}}
    function pklB64(buf){var b='';var by=new Uint8Array(buf);for(var i=0;i<by.byteLength;i++){b+=String.fromCharCode(by[i]);}return window.btoa(b);}
    function pklPost(url,fields){var b=new URLSearchParams();for(var k in fields){b.append(k,fields[k]);}return fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(function(r){return r.text().then(function(t){try{return JSON.parse(t);}catch(e){console.log('PasskeyLogin: endpoint returned non JSON (HTTP '+r.status+')');return null;}});});}

    function msg(text){
        var el = document.getElementById('pklStatus');
        if (!el) return;
        if (!text){ el.style.display='none'; el.textContent=''; return; }
        el.textContent = text;
        el.style.display = 'block';
    }

    // Self drawn remove confirmation rows.
    document.addEventListener('click', function(ev){
        var open = ev.target.closest ? ev.target.closest('.pkl-remove-open') : null;
        if (open){
            ev.preventDefault();
            var row = open.closest('.pkl-row');
            var conf = row ? row.querySelector('.pkl-confirm') : null;
            if (conf){ conf.style.display = 'flex'; open.style.visibility = 'hidden'; }
            return;
        }
        var cancel = ev.target.closest ? ev.target.closest('.pkl-remove-cancel') : null;
        if (cancel){
            ev.preventDefault();
            var row2 = cancel.closest('.pkl-row');
            if (row2){
                var conf2 = row2.querySelector('.pkl-confirm');
                var opener = row2.querySelector('.pkl-remove-open');
                if (conf2) conf2.style.display = 'none';
                if (opener) opener.style.visibility = 'visible';
            }
        }
    });

    var btn = document.getElementById('pklAddBtn');
    if (!btn) return;

    if (!window.PublicKeyCredential || !navigator.credentials || !window.fetch){
        btn.style.opacity = '0.55';
        btn.style.cursor = 'default';
        btn.addEventListener('click', function(){ msg(L.unsupported); });
        return;
    }

    var busy = false;
    btn.addEventListener('click', function(){
        if (busy) return;
        busy = true;
        msg(L.working);
        btn.style.opacity = '0.55';
        function done(text){ busy = false; btn.style.opacity = '1'; msg(text || ''); }

        pklPost(oUrl, {securityToken: tok}).then(function(d){
            if (!d || !d.ok || !d.createArgs){ done(d && d.error ? d.error : L.generic); return null; }
            pklToBuf(d.createArgs);
            return navigator.credentials.create(d.createArgs).then(function(cred){
                if (!cred){ done(L.generic); return; }
                var transports = [];
                try { if (cred.response.getTransports) transports = cred.response.getTransports(); } catch(e){}
                var payload = {
                    clientDataJSON: pklB64(cred.response.clientDataJSON),
                    attestationObject: pklB64(cred.response.attestationObject),
                    transports: transports
                };
                return pklPost(vUrl, {securityToken: tok, attestation: JSON.stringify(payload)}).then(function(v){
                    if (v && v.ok){ window.location.reload(); }
                    else { done(v && v.error ? v.error : L.generic); }
                });
            });
        }).catch(function(e){
            if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError')){ done(L.cancelled); }
            else if (e && e.name === 'InvalidStateError'){ done(L.already); }
            else { console.log('PasskeyLogin', e && e.name ? e.name : 'error'); done(L.generic); }
        });
    });
})();
</script>

<?php } ?>
</div>
