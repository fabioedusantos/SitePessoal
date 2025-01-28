(self["webpackChunk_canva_web"] = self["webpackChunk_canva_web"] || []).push([[5194],{

/***/ 437256:
function(_, __, __webpack_require__) {__webpack_require__.n_x = __webpack_require__.n;const __web_req__ = __webpack_require__;__web_req__(813110);self._fe4d99ebe0d2d259646a80d250150d47 = self._fe4d99ebe0d2d259646a80d250150d47 || {};(function(__c) {var gNb,q6,hNb,p6,iNb;gNb=function(){p6||(p6=__c.UGa());return p6};q6=function(a,b,c){let d=a.get(c);if(d)return d.disconnect(),d;d=b();a.set(c,d);return d};hNb=new WeakMap;
iNb=class{add(a){const b=q6(hNb,()=>this.YJ.createMediaElementSource(a),a),c=q6(this.Hva,()=>this.YJ.createGain(),a),d=q6(this.Iva,()=>this.YJ.createGain(),a),e=q6(this.Poa,()=>this.YJ.createGain(),a);b.connect(c);c.connect(d);d.connect(e);e.connect(this.YJ.destination);return()=>{b.disconnect();c.disconnect();d.disconnect();e.disconnect();this.Hva.delete(a);this.Iva.delete(a);this.Poa.delete(a)}}udb(a,b){const c=__c.C(this.Hva.get(a));a.volume=Math.min(1,b);c.gain.value=Math.max(1,b)}Hdb(a,b){__c.C(this.Iva.get(a)).gain.value=
b}resume(){for(const [a,b]of this.Poa.entries())b.gain.value=a.muted?0:1;this.YJ.resume()}stop(){this.YJ.suspend()}constructor(a=gNb()){this.YJ=a;this.Hva=new Map;this.Iva=new Map;this.Poa=new Map}};__c.ZEa={};__c.ZEa.VSa=iNb;
}).call(self, self._fe4d99ebe0d2d259646a80d250150d47);}

}])
//# sourceMappingURL=sourcemaps/e5c305297ae25250.js.map