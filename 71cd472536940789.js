(self["webpackChunk_canva_web"] = self["webpackChunk_canva_web"] || []).push([[382],{

/***/ 208016:
function(_, __, __webpack_require__) {__webpack_require__.n_x = __webpack_require__.n;const __web_req__ = __webpack_require__;__web_req__(813110);self._fe4d99ebe0d2d259646a80d250150d47 = self._fe4d99ebe0d2d259646a80d250150d47 || {};(function(__c) {var r6=function(a){__c.w(a.every((e,f)=>0===f?!0:e.x>a[f-1].x),"Points must be ordered strictly monotonically increasing in x");var b=a.length;const c=new Float64Array(b),d=new Float64Array(b);for(let e=1;e<b-1;e++){const f=a[e-1],g=a[e],h=a[e+1],k=h.x-f.x,l=(g.x-f.x)/k,m=l*d[e-1]+2;c[e]=(6*((h.y-g.y)/(h.x-g.x)-(g.y-f.y)/(g.x-f.x))/k-l*c[e-1])/m;d[e]=(l-1)/m}for(b-=2;0<=b;--b)d[b]=d[b]*d[b+1]+c[b];return new jNb(a,d)},kNb=function(){return!!window.createImageBitmap&&"undefined"!==typeof ImageBitmapRenderingContext},
lNb=function(a,{image:b,xq:c,width:d,height:e}){var f;a.pca.width=d;a.pca.height=e;const g=c.top,h=c.left,k=c.width;c=c.height;null===(f=a.teb)||void 0===f?void 0:f.drawImage(b,h,g,k,c,0,0,d,e)},s6=function(a){const b=r6(a.r),c=r6(a.g);a=r6(a.b);const d=new Uint8Array(768);for(let e=0;256>e;e++)d[3*e]=Math.max(0,Math.min(255,b.EA(e))),d[3*e+1]=Math.max(0,Math.min(255,c.EA(e))),d[3*e+2]=Math.max(0,Math.min(255,a.EA(e)));return new mNb({width:256,height:1,source:d})},oNb=function({filter:a,width:b,
height:c,f4:d}){const {r:e,g:f,b:g}=0!==a.Xy?nNb({h:((a.Xy/200+.5-1/7)%1+1)%1,s:1,ba:.5}):{r:0,g:0,b:0};return{D:a.brightness/200,E:a.contrast/100*.6,F:a.saturation/100+1,G:[e,f,g],s:(0<=a.blur?a.blur:3)*Math.max(b,c)/2E3,H:Math.min(Math.max(-a.blur,0),100),J:a.DJ/100,I:a.uJ/100*.1,N:a.lJ/100,v:a.sG/100,L:a.highlights/100,M:a.Ky/100,K:a.eH/100,O:a.jz/100*.7,d:d?1:0}},nNb=function({h:a,s:b,ba:c}){if(0===b)return{r:a,g:a,b:a};b=.5>c?c*(1+b):c+b-c*b;c=2*c-b;return{r:t6(c,b,a+1/3),g:t6(c,b,a),b:t6(c,
b,a-1/3)}},t6=function(a,b,c){0>c&&(c+=1);1<c&&--c;return c<1/6?a+6*(b-a)*c:.5>c?b:c<2/3?a+(b-a)*(2/3-c)*6:a},pNb=function(a,b){if(0===a.width&&0===a.height||0===b.width&&0===b.height)return!0;if(a.width===b.width&&a.height===b.height)return!1;const c=Math.trunc(a.width/a.height),d=Math.trunc(b.width/b.height);if(!(1<=c&&1<=d||1>c&&1>d))return!0;a=a.width*a.height;b=b.width*b.height;return b<=a?!1:2<b/a},qNb=function(a,b){return b.includes(1)&&("video"===a.type||"canvas"===a.type)},rNb=async function(a,
b,c,d){const e=a.width,f=a.height;kNb()&&d instanceof b.ImageBitmapRenderingContext?(a=await b.createImageBitmap(a),c.width=e,c.height=f,d.transferFromImageBitmap(a)):d instanceof b.CanvasRenderingContext2D&&(c.width=e,c.height=f,d.clearRect(0,0,e,f),d.drawImage(a,0,0,e,f))},sNb=function(){var a,b;return/Firefox/i.test(null===(b=window)||void 0===b?void 0:null===(a=b.navigator)||void 0===a?void 0:a.userAgent)},tNb=__webpack_require__(443763).jsx;var uNb=__webpack_require__,vNb=uNb(993864),wNb=uNb.n_x(vNb)();var xNb=__webpack_require__(620826).Pi;var yNb=__webpack_require__(875604),zNb=yNb.useEffect,ANb=yNb.useRef;var u6=__webpack_require__(474545),mNb=u6.BinaryTexture,BNb=u6.createWebGlRenderer,CNb=u6.Effect,DNb=u6.ImageTexture,ENb=u6.Renderer;var jNb=class{EA(a){var b=this.ps;const c=this.Tva;let d=0,e=this.ps.length-1;for(;1<e-d;){var f=e+d>>1;b[f].x>a?e=f:d=f}f=b[e];b=b[d];const g=f.x-b.x,h=(f.x-a)/g;a=(a-b.x)/g;return h*b.y+a*f.y+(c[d]*(h**3-h)+c[e]*(a**3-a))*g**2/6}constructor(a,b){this.ps=a;this.Tva=b}};var FNb=["precision mediump float;uniform sampler2D a;varying highp vec2 vTexCoord;uniform int d;void main(){if(d==0){gl_FragColor=texture2D(a,vTexCoord);return;}vec2 b=vec2(vTexCoord.x,vTexCoord.y/2.-1e-3);vec4 e=texture2D(a,b);vec2 c=vec2(vTexCoord.x,vTexCoord.y/2.+.499);vec4 f=texture2D(a,c);gl_FragColor=vec4(f.rgb,e.r);}","precision mediump float;uniform float D,E,F;uniform vec3 G;uniform sampler2D uPrevResult;varying vec2 vTexCoord;vec4 V(vec4 a,float b){return vec4(a.rgb+b,a.a);}vec4 W(vec4 b,float a){float c=a>0.?1./(1.-a):1.+a;return vec4((b.rgb-.5)*c+.5,b.a);}vec4 X(vec4 c,float a){vec3 d=vec3(.3086,.6094,.082),b=vec3(1.-a)*d;mat3 e=mat3(vec3(b.x)+vec3(a,0,0),vec3(b.y)+vec3(0,a,0),vec3(b.z)+vec3(0,0,a));return vec4(max(vec3(0),e*c.rgb),c.a);}vec4 Y(vec4 a,vec3 b){return vec4(a.rgb-(a.rgb-b)*.1,a.a);}void main(){gl_FragColor=texture2D(uPrevResult,vTexCoord);if(D!=0.)gl_FragColor=clamp(V(gl_FragColor,D),0.,1.);if(E!=0.)gl_FragColor=clamp(W(gl_FragColor,E),0.,1.);if(F!=1.)gl_FragColor=clamp(X(gl_FragColor,F),0.,1.);if(G!=vec3(0.,0.,0.))gl_FragColor=Y(gl_FragColor,G);}",
{fragment:"precision mediump float;uniform float s;uniform vec2 uResolution;uniform sampler2D uPrevResult;varying vec2 vTexCoord;float h(float b,float a){return .39894*exp(-.5*pow(b/a,2.))/a;}vec4 t(sampler2D d,vec2 e,vec2 q,float b){float f=0.;vec4 g=vec4(0.);vec2 r=1./uResolution;float c=h(0.,b);vec4 k=texture2D(d,e);k.rgb*=k.a,g+=k*c,f+=c;float u=b*2.5;for(float a=1.;a<=200.;a+=2.){if(a>u)break;float i=a+1.,l=h(a,b),m=h(i,b);c=l+m;vec2 j=q*(a*l+i*m)/c*r;vec4 n=texture2D(d,e+j),o=texture2D(d,e-j);n.rgb*=n.a,o.rgb*=o.a,g+=(n+o)*c,f+=c*2.;}vec4 p=g/f;p.rgb/=p.a;return p;}void main(){if(s==0.){gl_FragColor=texture2D(uPrevResult,vTexCoord);return;}gl_FragColor=t(uPrevResult,vTexCoord,vec2(1.,0.),s);}",
output:"C"},{fragment:"precision mediump float;uniform float s;uniform vec2 uResolution;uniform sampler2D C,uPrevResult;varying vec2 vTexCoord;float h(float b,float a){return .39894*exp(-.5*pow(b/a,2.))/a;}vec4 t(sampler2D d,vec2 e,vec2 q,float b){float f=0.;vec4 g=vec4(0.);vec2 r=1./uResolution;float c=h(0.,b);vec4 k=texture2D(d,e);k.rgb*=k.a,g+=k*c,f+=c;float u=b*2.5;for(float a=1.;a<=200.;a+=2.){if(a>u)break;float i=a+1.,l=h(a,b),m=h(i,b);c=l+m;vec2 j=q*(a*l+i*m)/c*r;vec4 n=texture2D(d,e+j),o=texture2D(d,e-j);n.rgb*=n.a,o.rgb*=o.a,g+=(n+o)*c,f+=c*2.;}vec4 p=g/f;p.rgb/=p.a;return p;}void main(){if(s==0.){gl_FragColor=texture2D(uPrevResult,vTexCoord);return;}gl_FragColor=t(C,vTexCoord,vec2(0.,1.),s);}",
inputs:["C"],output:"x"},{fragment:"precision mediump float;uniform float s,H,I,J;uniform sampler2D x,uPrevResult,z,A;varying vec2 vTexCoord;vec4 Z(vec4 a,vec4 c,float b){return b<=0.?a:mix(c,a,1.+b/20.);}vec4 _(vec4 a,float b){a.rgb=b>0.?mix(a.rgb,vec3(texture2D(A,vec2(a.r,.5)).r,texture2D(A,vec2(a.g,.5)).g,texture2D(A,vec2(a.b,.5)).b),b):mix(a.rgb,vec3(texture2D(z,vec2(a.r,.5)).r,texture2D(z,vec2(a.g,.5)).g,texture2D(z,vec2(a.b,.5)).b),-b);return a;}vec4 aa(vec4 a,float b){return vec4(a.r+b,a.g,a.b-b,a.a);}void main(){gl_FragColor=s==0.?texture2D(uPrevResult,vTexCoord):texture2D(x,vTexCoord);if(H!=0.){vec4 a=texture2D(uPrevResult,vTexCoord);gl_FragColor=clamp(Z(a,gl_FragColor,H),0.,1.);}if(J!=0.)gl_FragColor=clamp(_(gl_FragColor,J),0.,1.);if(I!=0.)gl_FragColor=aa(gl_FragColor,I);}",
inputs:["x"]},{fragment:"precision mediump float;uniform float v;uniform vec2 uResolution;uniform sampler2D uPrevResult;varying vec2 vTexCoord;float h(float b,float a){return .39894*exp(-.5*pow(b/a,2.))/a;}vec4 t(sampler2D d,vec2 e,vec2 q,float b){float f=0.;vec4 g=vec4(0.);vec2 r=1./uResolution;float c=h(0.,b);vec4 k=texture2D(d,e);k.rgb*=k.a,g+=k*c,f+=c;float u=b*2.5;for(float a=1.;a<=200.;a+=2.){if(a>u)break;float i=a+1.,l=h(a,b),m=h(i,b);c=l+m;vec2 j=q*(a*l+i*m)/c*r;vec4 n=texture2D(d,e+j),o=texture2D(d,e-j);n.rgb*=n.a,o.rgb*=o.a,g+=(n+o)*c,f+=c*2.;}vec4 p=g/f;p.rgb/=p.a;return p;}void main(){if(v==0.){gl_FragColor=texture2D(uPrevResult,vTexCoord);return;}gl_FragColor=t(uPrevResult,vTexCoord,vec2(1.,0.),15.);}",
output:"C"},{fragment:"precision mediump float;uniform float v;uniform vec2 uResolution;uniform sampler2D C,uPrevResult;varying vec2 vTexCoord;float h(float b,float a){return .39894*exp(-.5*pow(b/a,2.))/a;}vec4 t(sampler2D d,vec2 e,vec2 q,float b){float f=0.;vec4 g=vec4(0.);vec2 r=1./uResolution;float c=h(0.,b);vec4 k=texture2D(d,e);k.rgb*=k.a,g+=k*c,f+=c;float u=b*2.5;for(float a=1.;a<=200.;a+=2.){if(a>u)break;float i=a+1.,l=h(a,b),m=h(i,b);c=l+m;vec2 j=q*(a*l+i*m)/c*r;vec4 n=texture2D(d,e+j),o=texture2D(d,e-j);n.rgb*=n.a,o.rgb*=o.a,g+=(n+o)*c,f+=c*2.;}vec4 p=g/f;p.rgb/=p.a;return p;}void main(){if(v==0.){gl_FragColor=texture2D(uPrevResult,vTexCoord);return;}gl_FragColor=t(C,vTexCoord,vec2(0.,1.),15.);}",
inputs:["C"],output:"x"},{fragment:"precision mediump float;uniform float v,K,L,M,N,O;uniform vec2 uResolution;uniform sampler2D B,x,uPrevResult;varying vec2 vTexCoord;const mat4 y=mat4(.255,.341,.105,0.,.652,-.465,.319,0.,.093,.125,-.424,0.,0.,.5,.5,1.),P=mat4(1.,1.,1.,0.,1.944,-.748,-.08,0.,.793,0.,-2.161,0.,-1.368,.374,1.121,1.);float Q(vec3 a){vec3 b=vec3(.2125,.7154,.0721);return dot(a,b);}float R(in vec2 a){a=a*2.-1.,a.y*=1.25-.5*step(0.,a.y),a.xy*=a.xy,a.xy=clamp(a.xy,0.,1.),a*=255.,a=floor(a);return sqrt(clamp(a.x+a.y,0.,255.)/255.);}vec4 S(vec4 c,float a){vec3 d=vec3(.2125,.7154,.0721),b=vec3(1.-a)*d;mat3 e=mat3(vec3(b.x)+vec3(a,0,0),vec3(b.y)+vec3(0,a,0),vec3(b.z)+vec3(0,0,a));return vec4(clamp(e*c.rgb,0.,1.),c.a);}float T(in float a){return pow(1.-a,3.);}vec4 ba(vec4 a,float c){const mat3 d=mat3(.334,0,-.5,.333,.434,.25,.333,-.433,.251);vec3 b=d*a.rgb;b.gb+=.5;float e=R(b.gb),f=T(clamp(e,0.,1.));vec4 g=f*a,i=S(g,1.+c);return a.a*i+(1.-i.a)*a;}float w(float c,float a,float d,float b,float e){return b+(e-b)*(c-a)/(d-a);}float U(float a){float c=1.-step(.1429,a),d=step(.1429,a)-step(.2857,a),e=step(.2857,a)-step(.7143,a),f=step(.7143,a)-step(.8571,a),g=step(.8571,a),b=w(a,0.,.1429,0.,.75)*c;b+=w(a,.1429,.2857,.75,1.)*d,b+=w(a,.2857,.7143,1.,1.)*e,b+=w(a,.7143,.8571,1.,.75)*f,b+=w(a,.8571,1.,.75,0.)*g;return b;}vec4 ca(vec4 a,vec2 e,float f){float g=Q(a.rgb);vec4 b=vec4(a.rgb,a.a*U(g)),d=texture2D(x,e);b.rgb*=b.a,a.rgb*=a.a,d.a=1.;vec4 c=mix(d,b,1.+f);c=clamp(c,0.,1.),a=a.a*c+(1.-c.a)*a,a.rgb/=a.a;return a;}vec4 da(vec4 b,float c){vec4 a=y*b;float d=.8*step(0.,c)+.2,e=pow(a.r,3.),f=clamp(a.r*(1.+c*d),0.,1.);a.r=mix(a.r,f,e),b=P*a,b.r=clamp(b.r,0.,1.);return b;}vec4 ea(vec4 b,float c){vec4 a=y*b;float d=pow(1.-a.r,3.),e=clamp(a.r*(1.+c),0.,1.);a.r=mix(a.r,e,d),b=P*a,b.r=clamp(b.r,0.,1.);return b;}vec4 fa(vec4 a,float b){return vec4(mix(a.rgb,vec3(texture2D(B,vec2(a.r,.5)).r,texture2D(B,vec2(a.g,.5)).g,texture2D(B,vec2(a.b,.5)).b),b),a.a);}vec4 ga(vec4 b,vec2 d,vec2 a,float f){b.rgb*=b.a;float c=min(a.x,a.y);vec2 e=(d*2.-1.)/vec2(a.y/c,a.x/c);float g=clamp((length(e)-.7)/.6,0.,1.);b=mix(b,vec4(0.,0.,0.,1.),g*f),b.rgb/=b.a;return b;}void main(){gl_FragColor=texture2D(uPrevResult,vTexCoord);if(v!=0.)gl_FragColor=clamp(ca(gl_FragColor,vTexCoord,v),0.,1.);if(N!=0.)gl_FragColor=clamp(ba(gl_FragColor,N),0.,1.);if(L!=0.)gl_FragColor=clamp(da(gl_FragColor,L),0.,1.);if(M!=0.)gl_FragColor=clamp(ea(gl_FragColor,M),0.,1.);if(O!=0.)gl_FragColor=clamp(ga(gl_FragColor,vTexCoord,uResolution,O),0.,1.);if(K!=0.)gl_FragColor=fa(gl_FragColor,K);}",
inputs:["x"]}],INb=class{initialize(a){a=new DNb({source:a.element});var b={a,A:s6(GNb),z:s6(HNb),B:s6({r:v6,g:v6,b:v6})};b=new CNb({passes:FNb,textures:b,uniforms:oNb({filter:__c.aS,width:0,height:0,f4:!1})});const c=document.createElement("div"),d=new ENb(()=>BNb(c,{env:{devicePixelRatio:1}}));d.onContextLost=()=>{d.restore()};d.load([a]);d.load(b);this.aS.width&&this.aS.height&&d.resize(this.aS.width,this.aS.height);this.renderer=d;this.effect=b;this.f1=a;this.Kma=!0;this.uf=setTimeout(()=>this.dispose(),
16E3)}render({source:a,filter:b,f4:c,width:d,height:e}){this.Kma||this.initialize(a);if(!this.renderer||!this.effect||!this.f1)throw Error("Renderer is not initialized");this.uf&&clearTimeout(this.uf);this.uf=setTimeout(()=>this.dispose(),16E3);pNb(this.aS,{width:d,height:e})&&(this.renderer.resize(d,e),this.aS={width:d,height:e});this.Q6a!==a&&("sprite"===a.type?(lNb(this,{image:a.element,xq:a.xq,width:d,height:e}),this.f1.source=this.pca):this.f1.source=a.element,this.Q6a=a);this.renderer.update([this.f1]);
this.effect.update({textures:{a:this.f1},uniforms:oNb({filter:null!==b&&void 0!==b?b:__c.aS,width:d,height:e,f4:c})});this.renderer.render(this.effect);return this.renderer.canvas}dispose(){var a;this.uf&&clearTimeout(this.uf);null===(a=this.renderer)||void 0===a?void 0:a.dispose();this.uf=this.effect=this.renderer=void 0;this.Kma=!1}constructor(){this.Kma=!1;this.pca=document.createElement("canvas");this.teb=this.pca.getContext("2d");this.aS={width:0,height:0}}},v6=[{x:0,y:69},{x:79,y:96},{x:127,
y:127},{x:255,y:225}],GNb={r:[{x:0,y:0},{x:77,y:26},{x:179,y:204},{x:255,y:255}],g:[{x:0,y:0},{x:64,y:38},{x:191,y:217},{x:255,y:255}],b:[{x:0,y:0},{x:85,y:102},{x:170,y:153},{x:255,y:204}]},HNb={r:[{x:0,y:0},{x:77,y:26},{x:179,y:51},{x:242,y:255}],g:[{x:0,y:0},{x:64,y:102},{x:153,y:153},{x:255,y:255}],b:[{x:0,y:0},{x:77,y:79},{x:153,y:153},{x:255,y:255}]};var JNb;({m0a:JNb}={m0a:new INb});
__c.gkb={PTa:xNb(({source:a,filter:b,modifiers:c,L:d,paused:e,Trb:f,objectFit:g,Jy:h})=>{var k;const l=ANb(null),m=(null===(k=l.current)||void 0===k?void 0:k.ownerDocument.defaultView)||window;zNb(()=>{if(l.current){var n=l.current,p=kNb()&&!sNb()?n.getContext("bitmaprenderer"):n.getContext("2d");if(p){var q=()=>{var u;{let y=u=0;if(d)u=d.width,y=d.height;else switch(a.type){case "video":u=a.element.videoWidth;y=a.element.videoHeight;break;case "sprite":u=a.xq.width;y=a.xq.height;break;case "image":case "canvas":u=
a.element.width,y=a.element.height}y=qNb(a,c)?y/2:y;var v,x;/iphone|android/i.test(null===(x=window)||void 0===x?void 0:null===(v=x.navigator)||void 0===v?void 0:v.userAgent)&&(v=u/y,x=1<v,u=Math.round(Math.min(u,x?720*v:720)),y=Math.round(Math.min(y,x?720:720/v)));const {width:z,height:A}={width:u,height:y};u=0!==z&&0!==A?JNb.render({source:a,filter:b,f4:qNb(a,c),width:z,height:A}):void 0}u&&(h&&h(u),rNb(u,m,n,p))},r=0;if("image"===a.type||"sprite"===a.type||e){const u=()=>{a:switch(a.type){case "image":case "sprite":var v=
a.element.complete;break a;case "video":v=a.element.readyState>=HTMLMediaElement.HAVE_CURRENT_DATA;break a;case "canvas":v=!0;break a;default:throw new __c.D(a);}v?q():r=requestAnimationFrame(u)};r=requestAnimationFrame(u);return()=>{cancelAnimationFrame(r)}}var t=()=>{q();r=requestAnimationFrame(t)};r=requestAnimationFrame(t);return()=>{cancelAnimationFrame(r)}}}},[m,a,b,c,d,e,null===b||void 0===b?void 0:b.brightness,null===b||void 0===b?void 0:b.contrast,null===b||void 0===b?void 0:b.saturation,
null===b||void 0===b?void 0:b.Xy,null===b||void 0===b?void 0:b.blur,null===b||void 0===b?void 0:b.DJ,null===b||void 0===b?void 0:b.uJ,null===b||void 0===b?void 0:b.sG,null===b||void 0===b?void 0:b.lJ,null===b||void 0===b?void 0:b.highlights,null===b||void 0===b?void 0:b.Ky,null===b||void 0===b?void 0:b.jz,null===b||void 0===b?void 0:b.eH,h]);return tNb("canvas",{ref:l,className:wNb("w_xYrQ",{_0frnRw:f,BhGlcg:"fill"===g,shq_KQ:"cover"===g,NepMWw:"contain"===g})})})};
}).call(self, self._fe4d99ebe0d2d259646a80d250150d47);}

}])
//# sourceMappingURL=sourcemaps/71cd472536940789.js.map