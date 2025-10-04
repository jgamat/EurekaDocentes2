(function(){
  // Helper that returns the placeholder tab HTML with spinner and multiple redirect fallbacks
  if (!window.__imprPlaceholderHtml) {
    window.__imprPlaceholderHtml = function(){
      var base = window.__fallbackBase || '';
      var uid = (window.__uid == null ? 'guest' : window.__uid);
      var html = '<!doctype html><html><head><meta charset="utf-8"><title>Generando PDF…</title>'+
        '<style>body{font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,\'Noto Sans\',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8fafc;color:#334155}'+
        '.wrap{display:flex;flex-direction:column;align-items:center;gap:12px;padding:24px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.05)}'+
        '.spinner{width:28px;height:28px;border:3px solid #cbd5e1;border-top-color:#6366f1;border-radius:50%;animation:spin .9s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}'+
        '.msg{font-weight:600} .hint{font-size:12px;color:#64748b;text-align:center;max-width:360px}'+
        '</style></head><body>'+
        '<div class="wrap"><div class="spinner"></div><div class="msg">Generando PDF…</div><div class="hint">Esta pestaña redirigirá automáticamente cuando el PDF esté listo.</div></div>'+
        '<script>(function(){'+
        'function nav(u){ if(!u) return; try{ location.replace(u);}catch(e){ location.href=u; } }'+
        'window.addEventListener("message", function(e){ try{ if(typeof e.data==="string" && e.data.indexOf("/storage/")===0){ nav(e.data); } }catch(_){} }, false);'+
        'var tries=0; var t=setInterval(function(){ tries++; try{ var u=(window.opener&&window.opener.__pdfUrl)?window.opener.__pdfUrl:null; if(u){ clearInterval(t); nav(u); return; } }catch(_){}'+
        'try{ var x=new XMLHttpRequest(); x.open("GET", "'+ base + '/pdf_url_user_' + (uid || 'guest') + '.txt?ts="+Date.now(), true);'+
        'x.onreadystatechange=function(){ if(x.readyState===4 && x.status===200){ var url=x.responseText && x.responseText.trim(); if(url && url.indexOf("/storage/")===0){ clearInterval(t); nav(url); } } };'+
        'x.send(); }catch(e){} if(tries>200){ clearInterval(t); } }, 500);'+
        '})();<\/script></body></html>';
      return html;
    };
  }

  // Alpine data factory for the page
  window.imprCredData = function(pending){
    return {
      pdfWin: null,
      pdfUrl: null,
      blocked: false,
      isGenerating: false,
      pendingPdfUrl: pending,
      openPlaceholder(){
        this.blocked = false;
        this.isGenerating = true;
        try {
          this.pdfWin = window.open('about:blank', '_blank');
          if (!this.pdfWin || this.pdfWin.closed) {
            this.blocked = true;
            this.isGenerating = false;
          }
          if (this.pdfWin && !this.pdfWin.closed) {
            try {
              const html = (window.__imprPlaceholderHtml ? window.__imprPlaceholderHtml() : '<!doctype html><title>Generando...</title>');
              this.pdfWin.document.open();
              this.pdfWin.document.write(html);
              this.pdfWin.document.close();
            } catch (_) {}
          }
        } catch (e) {
          this.blocked = true;
          this.pdfWin = null;
          this.isGenerating = false;
        }
      },
      openPdf(url){
        if (!url) return;
        this.pdfUrl = url;
        window.__pdfUrl = url;
        try {
          if (this.pdfWin && !this.pdfWin.closed) {
            const w = this.pdfWin;
            setTimeout(() => { try { w.location.replace(url); } catch (_) { w.location.href = url; } }, 0);
            try { w.postMessage(url, '*'); } catch (_) {}
            this.blocked = false;
            this.pdfWin = null;
            this.isGenerating = false;
            return;
          }
          const w = window.open(url, '_blank');
          if (!w || w.closed) {
            this.blocked = true;
          }
          this.isGenerating = false;
        } catch (e) {
          this.blocked = true;
          this.isGenerating = false;
        }
      }
    };
  };
})();
