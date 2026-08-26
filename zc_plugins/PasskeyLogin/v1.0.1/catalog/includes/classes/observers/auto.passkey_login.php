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
// Injects the conditional UI passkey script on the login page and the
// account tile plus enrollment nudge on the account page, all at
// NOTIFY_FOOTER_END. No template edits: without JavaScript the pages
// render identical to stock, which is correct because WebAuthn itself
// requires JavaScript.
//
// All injected UI is self-contained inline styling, so nothing depends on
// theme button or form-control CSS. Every language string that lands in
// JavaScript passes through json_encode. AJAX POSTs carry the session
// securityToken and target index.php URLs directly, never zen_href_link,
// because SEO rewriters can drop query params on pretty URLs.
//
if (!defined('PKL_PLUGIN_DIR')) define('PKL_PLUGIN_DIR', dirname(__DIR__, 3));
require_once PKL_PLUGIN_DIR . '/includes/classes/PasskeyLoginService.php';

class zcObserverPasskeyLogin extends base
{
    public function __construct()
    {
        $this->attach($this, ['NOTIFY_FOOTER_END']);
    }

    public function updateNotifyFooterEnd(&$class, $eventID)
    {
        global $current_page_base;

        if (!pkl_is_available()) return;

        $page = $current_page_base ?? ($_GET['main_page'] ?? '');

        if ($page === 'login' && !PasskeyLoginService::isRealCustomerSession()) {
            $this->emitConditionalLogin();
            return;
        }

        if ($page === 'account' && PasskeyLoginService::isRealCustomerSession()) {
            $this->emitAccountEnhancements();
        }
    }

    /* ------------------------------------------------------------------ */
    /* Login page: conditional UI (passkey inside the email autofill)      */
    /* ------------------------------------------------------------------ */

    protected function emitConditionalLogin(): void
    {
        $token = $_SESSION['securityToken'] ?? '';
        if ($token === '') return;

        $optionsUrl = 'index.php?main_page=passkey_settings&action=ajax_login_options';
        $verifyUrl  = 'index.php?main_page=passkey_settings&action=ajax_login_verify';

        $js = '<script>(function(){' . "\n"
            . 'if(!window.PublicKeyCredential||!window.fetch)return;' . "\n"
            . 'var tok=' . json_encode($token) . ';' . "\n"
            . 'var oUrl=' . json_encode($optionsUrl) . ';' . "\n"
            . 'var vUrl=' . json_encode($verifyUrl) . ';' . "\n"
            . 'var loginErr=' . json_encode(defined('PKL_ERROR_LOGIN_FAILED') ? PKL_ERROR_LOGIN_FAILED : 'We could not sign you in with that passkey. Please try another sign in option.') . ';' . "\n"
            . $this->jsHelpers()
            . 'function pklLoginMsg(text){if(!text)return;var old=document.getElementById("pklLoginStatus");if(old){old.textContent=text;return;}var em=document.getElementById("login-email-address")||document.querySelector("input[name=\"email_address\"]");if(!em)return;var el=document.createElement("div");el.id="pklLoginStatus";el.setAttribute("role","alert");el.setAttribute("style","margin:10px 0;padding:10px 12px;border:1px solid #b84a4a;border-radius:6px;background:#fff7f7;color:#7a1f1f;font-size:0.95em;");el.textContent=text;var host=em.closest("form")||em.parentNode;if(host){if(em.parentNode){em.parentNode.insertBefore(el,em.nextSibling);}else{host.appendChild(el);}}}' . "\n"
            . 'var cap=(PublicKeyCredential.isConditionalMediationAvailable?PublicKeyCredential.isConditionalMediationAvailable():Promise.resolve(false));' . "\n"
            . 'cap.then(function(ok){' . "\n"
            . 'if(!ok)return;' . "\n"
            . 'var em=document.getElementById("login-email-address")||document.querySelector("input[name=\"email_address\"]");' . "\n"
            . 'if(em)em.setAttribute("autocomplete","username webauthn");' . "\n"
            . 'var pklArmed=false;' . "\n"
            . 'function pklArm(){' . "\n"
            . 'if(pklArmed)return;' . "\n"
            . 'pklArmed=true;' . "\n"
            . 'var ac=(typeof AbortController==="function")?new AbortController():null;' . "\n"
            . 'var timer=setTimeout(function(){pklArmed=false;if(ac){try{ac.abort();}catch(x){}}pklArm();},240000);' . "\n"
            . 'pklPost(oUrl,{securityToken:tok}).then(function(d){' . "\n"
            . 'if(!d||!d.ok||!d.getArgs){if(timer)clearTimeout(timer);pklArmed=true;return null;}' . "\n"
            . 'pklToBuf(d.getArgs);' . "\n"
            . 'd.getArgs.mediation="conditional";' . "\n"
            . 'if(ac)d.getArgs.signal=ac.signal;' . "\n"
            . 'return navigator.credentials.get(d.getArgs);' . "\n"
            . '}).then(function(cred){' . "\n"
            . 'if(!cred)return;' . "\n"
            . 'if(timer)clearTimeout(timer);' . "\n"
            . 'var a={id:cred.rawId?pklB64(cred.rawId):null,' . "\n"
            . 'clientDataJSON:pklB64(cred.response.clientDataJSON),' . "\n"
            . 'authenticatorData:pklB64(cred.response.authenticatorData),' . "\n"
            . 'signature:pklB64(cred.response.signature),' . "\n"
            . 'userHandle:cred.response.userHandle?pklB64(cred.response.userHandle):null};' . "\n"
            . 'pklPost(vUrl,{securityToken:tok,assertion:JSON.stringify(a)}).then(function(v){' . "\n"
            . 'if(v&&v.ok&&v.redirect){window.location.href=v.redirect;}' . "\n"
            . 'else{pklLoginMsg(v&&v.error?v.error:loginErr);}' . "\n"
            . '});' . "\n"
            . '}).catch(function(e){' . "\n"
            . 'if(e&&(e.name==="AbortError"||e.name==="NotAllowedError"))return;' . "\n"
            . 'console.log("PasskeyLogin conditional UI error",e&&e.name?e.name:"error");' . "\n"
            . '});' . "\n"
            . '}' . "\n"
            . 'pklArm();' . "\n"
            . '});' . "\n"
            . '})();</script>';

        echo $js;
    }

    /* ------------------------------------------------------------------ */
    /* Account page: tile plus enrollment nudge                            */
    /* ------------------------------------------------------------------ */

    protected function emitAccountEnhancements(): void
    {
        $cid = (int)$_SESSION['customer_id'];
        $token = $_SESSION['securityToken'] ?? '';

        $tileUrl = zen_href_link(FILENAME_PASSKEY_SETTINGS, '', 'SSL');
        $keyCount = PasskeyLoginService::countForCustomer($cid);
        $showNudge = ($keyCount === 0)
            && defined('PKL_NUDGE_ENABLED') && PKL_NUDGE_ENABLED === 'true'
            && !PasskeyLoginService::nudgeOptedOut($cid)
            && $token !== '';

        $optoutUrl = 'index.php?main_page=passkey_settings&action=ajax_nudge_optout';

        $js = '<script>(function(){' . "\n"
            . 'var ul=document.getElementById("myAccountGen");' . "\n"
            . 'if(ul){' . "\n"
            . 'var li=document.createElement("li");' . "\n"
            . 'var a=document.createElement("a");' . "\n"
            . 'a.href=' . json_encode($tileUrl) . ';a.className="acct-tile";' . "\n"
            . 'a.innerHTML=\'<span class="acct-tile-icon"><i class="fa-solid fa-fingerprint" aria-hidden="true"></i></span>\';' . "\n"
            . 'var lb=document.createElement("span");lb.className="acct-tile-label";' . "\n"
            . 'lb.textContent=' . json_encode(defined('PKL_TILE_LABEL') ? PKL_TILE_LABEL : 'Passkeys') . ';' . "\n"
            . 'a.appendChild(lb);li.appendChild(a);ul.appendChild(li);' . "\n"
            . '}' . "\n";

        if ($showNudge) {
            $js .= 'var capN=(window.PublicKeyCredential&&window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable?window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable():Promise.resolve(false));' . "\n"
                . 'capN.then(function(ok){' . "\n"
                . 'if(!ok)return;' . "\n"
                . 'var anchor=document.getElementById("myAccountGen");' . "\n"
                . 'if(!anchor)return;' . "\n"
                . 'var host=anchor.closest("section")||document.getElementById("accountLinksWrapper")||anchor;' . "\n"
                . 'var card=document.createElement("div");' . "\n"
                . 'card.setAttribute("style","margin:0 0 18px;padding:16px 18px;border:1px solid #d8d2c4;border-radius:10px;background:#faf8f3;display:flex;flex-wrap:wrap;align-items:center;gap:12px;");' . "\n"
                . 'var txt=document.createElement("div");txt.setAttribute("style","flex:1 1 260px;min-width:220px;");' . "\n"
                . 'var h=document.createElement("div");h.setAttribute("style","font-weight:700;font-size:1.05em;margin:0 0 4px;");' . "\n"
                . 'h.textContent=' . json_encode(defined('PKL_NUDGE_TITLE') ? PKL_NUDGE_TITLE : 'Sign in faster next time') . ';' . "\n"
                . 'var p=document.createElement("div");p.setAttribute("style","font-size:0.95em;opacity:0.85;");' . "\n"
                . 'p.textContent=' . json_encode(defined('PKL_NUDGE_TEXT') ? PKL_NUDGE_TEXT : 'Add a passkey and sign in with your fingerprint, face, or device PIN. No password to type.') . ';' . "\n"
                . 'txt.appendChild(h);txt.appendChild(p);card.appendChild(txt);' . "\n"
                . 'var actions=document.createElement("div");actions.setAttribute("style","display:flex;gap:10px;align-items:center;flex-wrap:wrap;");' . "\n"
                . 'var go=document.createElement("a");go.href=' . json_encode($tileUrl) . ';' . "\n"
                . 'go.setAttribute("style","display:inline-block;padding:10px 18px;background:#2f2a22;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;white-space:nowrap;");' . "\n"
                . 'go.textContent=' . json_encode(defined('PKL_NUDGE_ADD_BUTTON') ? PKL_NUDGE_ADD_BUTTON : 'Add a Passkey') . ';' . "\n"
                . 'var no=document.createElement("a");no.href="#";' . "\n"
                . 'no.setAttribute("style","display:inline-block;padding:10px 12px;color:#6b6455;text-decoration:underline;font-size:0.92em;white-space:nowrap;cursor:pointer;");' . "\n"
                . 'no.textContent=' . json_encode(defined('PKL_NUDGE_DISMISS') ? PKL_NUDGE_DISMISS : 'No Thanks') . ';' . "\n"
                . 'no.addEventListener("click",function(ev){ev.preventDefault();' . "\n"
                . 'var b=new URLSearchParams();b.append("securityToken",' . json_encode($token) . ');' . "\n"
                . 'fetch(' . json_encode($optoutUrl) . ',{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:b.toString()});' . "\n"
                . 'card.parentNode.removeChild(card);});' . "\n"
                . 'actions.appendChild(go);actions.appendChild(no);card.appendChild(actions);' . "\n"
                . 'host.insertAdjacentElement("beforebegin",card);' . "\n"
                . '});' . "\n";
        }

        $js .= '})();</script>';
        echo $js;
    }

    /* ------------------------------------------------------------------ */
    /* Shared JS helpers (ported from the lbuchs client reference)         */
    /* ------------------------------------------------------------------ */

    protected function jsHelpers(): string
    {
        return 'function pklToBuf(o){var p="=?BINARY?B?",s="?=";if(typeof o==="object"&&o!==null){for(var k in o){if(typeof o[k]==="string"){var v=o[k];if(v.substring(0,p.length)===p&&v.substring(v.length-s.length)===s){v=v.substring(p.length,v.length-s.length);var bs=window.atob(v);var by=new Uint8Array(bs.length);for(var i=0;i<bs.length;i++){by[i]=bs.charCodeAt(i);}o[k]=by.buffer;}}else{pklToBuf(o[k]);}}}}' . "\n"
            . 'function pklB64(buf){var b="";var by=new Uint8Array(buf);for(var i=0;i<by.byteLength;i++){b+=String.fromCharCode(by[i]);}return window.btoa(b);}' . "\n"
            . 'function pklPost(url,fields){var b=new URLSearchParams();for(var k in fields){b.append(k,fields[k]);}return fetch(url,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:b.toString()}).then(function(r){return r.text().then(function(t){try{return JSON.parse(t);}catch(e){console.log("PasskeyLogin: endpoint returned non JSON (HTTP "+r.status+")");return null;}});});}' . "\n";
    }
}
