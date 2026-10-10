/* Progressive-enhancement SVG charts. All values come from account-scoped PHP
 * datasets; chart code does not fetch or upload personal data. */
(function(){
'use strict';
var NS='http://www.w3.org/2000/svg';
function el(tag,attributes,parent,text){
 var node=document.createElementNS(NS,tag);
 Object.keys(attributes||{}).forEach(function(k){node.setAttribute(k,String(attributes[k]));});
 if(typeof text!=='undefined'){node.textContent=String(text);}
 if(parent){parent.appendChild(node);}
 return node;
}
function plot(root){
 var data;
 try{data=JSON.parse(root.getAttribute('data-series')||'{}');}catch(e){return;}
 var months=Array.isArray(data.months)?data.months.slice(0,6):[];
 var series=data.series||{},labels=data.labels||{};
 var buttons=root.querySelectorAll('[data-series-key]');
 var container=root.querySelector('[data-chart-plot]');
 var summary=root.querySelector('[data-chart-summary]');
 if(!container||!months.length){return;}
 function render(key){
  var values=Array.isArray(series[key])?series[key].slice(0,months.length).map(function(v){return Math.max(0,Math.floor(Number(v)||0));}):[];
  if(!values.length){return;}
  buttons.forEach(function(b){b.setAttribute('aria-pressed',b.getAttribute('data-series-key')===key?'true':'false');});
  while(container.firstChild){container.removeChild(container.firstChild);}
  var svg=el('svg',{viewBox:'0 0 680 250',role:'img','aria-label':(labels[key]||key)+' per month'},container);
  el('title',{},svg,(labels[key]||key)+' six-month trend');
  var left=42,right=20,top=20,bottom=41,w=680-left-right,h=250-top-bottom;
  var max=Math.max(1,Math.max.apply(null,values)),ceil=Math.max(1,Math.ceil(max/4)*4);
  for(var i=0;i<=4;i++){
   var yy=top+(h*i/4);
   el('line',{x1:left,y1:yy,x2:680-right,y2:yy,class:'im-grid'},svg);
   el('text',{x:left-8,y:yy+4,'text-anchor':'end'},svg,Math.round(ceil*(4-i)/4));
  }
  var points=values.map(function(v,i){return {
   x:left+(months.length>1?i*w/(months.length-1):w/2),
   y:top+h-h*v/ceil
  };});
  var d=points.map(function(p,i){return(i?'L':'M')+p.x.toFixed(2)+' '+p.y.toFixed(2);}).join(' ');
  el('path',{d:d,class:'im-line'},svg);
  points.forEach(function(p,i){
   var dot=el('circle',{cx:p.x,cy:p.y,r:5,class:'im-point'},svg);
   el('title',{},dot,months[i]+': '+values[i]);
   el('text',{x:p.x,y:241,'text-anchor':'middle'},svg,months[i].slice(5));
  });
  if(summary){summary.textContent=(labels[key]||key)+': '+values.reduce(function(a,b){return a+b;},0).toLocaleString()+' entries during the six displayed months.';}
 }
 buttons.forEach(function(b){b.addEventListener('click',function(){render(b.getAttribute('data-series-key'));});});
 var first=buttons.length?buttons[0].getAttribute('data-series-key'):Object.keys(labels)[0];
 if(first){render(first);}
}
document.querySelectorAll('[data-ino-membership-chart]').forEach(plot);
}());
