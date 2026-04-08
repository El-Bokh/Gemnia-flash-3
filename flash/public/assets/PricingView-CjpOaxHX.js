import{$n as e,Dt as t,Ft as n,Gt as r,It as i,Lt as a,Mt as o,On as s,Pn as c,Qt as l,Rt as u,Vt as d,Wt as f,bn as p,cn as m,dn as h,kt as g,l as _,ln as v,mt as y,n as b,nr as x,on as S,r as C,rn as w,t as T,tr as E,un as D,v as O,wt as k,yn as A,zt as j}from"./_plugin-vue_export-helper-BQA7LogN.js";import{h as ee,p as te,s as ne,x as re}from"./index-PbOyk_DG.js";import{t as M}from"./useSeo-D01WZnUZ.js";import{t as N}from"./baseeditableholder-DgNKW4sN.js";import{t as P}from"./tag-pj7G89Uv.js";var F=O.extend({name:`togglebutton`,style:`
    .p-togglebutton {
        display: inline-flex;
        cursor: pointer;
        user-select: none;
        overflow: hidden;
        position: relative;
        color: dt('togglebutton.color');
        background: dt('togglebutton.background');
        border: 1px solid dt('togglebutton.border.color');
        padding: dt('togglebutton.padding');
        font-size: 1rem;
        font-family: inherit;
        font-feature-settings: inherit;
        transition:
            background dt('togglebutton.transition.duration'),
            color dt('togglebutton.transition.duration'),
            border-color dt('togglebutton.transition.duration'),
            outline-color dt('togglebutton.transition.duration'),
            box-shadow dt('togglebutton.transition.duration');
        border-radius: dt('togglebutton.border.radius');
        outline-color: transparent;
        font-weight: dt('togglebutton.font.weight');
    }

    .p-togglebutton-content {
        display: inline-flex;
        flex: 1 1 auto;
        align-items: center;
        justify-content: center;
        gap: dt('togglebutton.gap');
        padding: dt('togglebutton.content.padding');
        background: transparent;
        border-radius: dt('togglebutton.content.border.radius');
        transition:
            background dt('togglebutton.transition.duration'),
            color dt('togglebutton.transition.duration'),
            border-color dt('togglebutton.transition.duration'),
            outline-color dt('togglebutton.transition.duration'),
            box-shadow dt('togglebutton.transition.duration');
    }

    .p-togglebutton:not(:disabled):not(.p-togglebutton-checked):hover {
        background: dt('togglebutton.hover.background');
        color: dt('togglebutton.hover.color');
    }

    .p-togglebutton.p-togglebutton-checked {
        background: dt('togglebutton.checked.background');
        border-color: dt('togglebutton.checked.border.color');
        color: dt('togglebutton.checked.color');
    }

    .p-togglebutton-checked .p-togglebutton-content {
        background: dt('togglebutton.content.checked.background');
        box-shadow: dt('togglebutton.content.checked.shadow');
    }

    .p-togglebutton:focus-visible {
        box-shadow: dt('togglebutton.focus.ring.shadow');
        outline: dt('togglebutton.focus.ring.width') dt('togglebutton.focus.ring.style') dt('togglebutton.focus.ring.color');
        outline-offset: dt('togglebutton.focus.ring.offset');
    }

    .p-togglebutton.p-invalid {
        border-color: dt('togglebutton.invalid.border.color');
    }

    .p-togglebutton:disabled {
        opacity: 1;
        cursor: default;
        background: dt('togglebutton.disabled.background');
        border-color: dt('togglebutton.disabled.border.color');
        color: dt('togglebutton.disabled.color');
    }

    .p-togglebutton-label,
    .p-togglebutton-icon {
        position: relative;
        transition: none;
    }

    .p-togglebutton-icon {
        color: dt('togglebutton.icon.color');
    }

    .p-togglebutton:not(:disabled):not(.p-togglebutton-checked):hover .p-togglebutton-icon {
        color: dt('togglebutton.icon.hover.color');
    }

    .p-togglebutton.p-togglebutton-checked .p-togglebutton-icon {
        color: dt('togglebutton.icon.checked.color');
    }

    .p-togglebutton:disabled .p-togglebutton-icon {
        color: dt('togglebutton.icon.disabled.color');
    }

    .p-togglebutton-sm {
        padding: dt('togglebutton.sm.padding');
        font-size: dt('togglebutton.sm.font.size');
    }

    .p-togglebutton-sm .p-togglebutton-content {
        padding: dt('togglebutton.content.sm.padding');
    }

    .p-togglebutton-lg {
        padding: dt('togglebutton.lg.padding');
        font-size: dt('togglebutton.lg.font.size');
    }

    .p-togglebutton-lg .p-togglebutton-content {
        padding: dt('togglebutton.content.lg.padding');
    }

    .p-togglebutton-fluid {
        width: 100%;
    }
`,classes:{root:function(e){var t=e.instance,n=e.props;return[`p-togglebutton p-component`,{"p-togglebutton-checked":t.active,"p-invalid":t.$invalid,"p-togglebutton-fluid":n.fluid,"p-togglebutton-sm p-inputfield-sm":n.size===`small`,"p-togglebutton-lg p-inputfield-lg":n.size===`large`}]},content:`p-togglebutton-content`,icon:`p-togglebutton-icon`,label:`p-togglebutton-label`}}),I={name:`BaseToggleButton`,extends:N,props:{onIcon:String,offIcon:String,onLabel:{type:String,default:`Yes`},offLabel:{type:String,default:`No`},readonly:{type:Boolean,default:!1},tabindex:{type:Number,default:null},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null},size:{type:String,default:null},fluid:{type:Boolean,default:null}},style:F,provide:function(){return{$pcToggleButton:this,$parentInstance:this}}};function L(e){"@babel/helpers - typeof";return L=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},L(e)}function R(e,t,n){return(t=z(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function z(e){var t=B(e,`string`);return L(t)==`symbol`?t:t+``}function B(e,t){if(L(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if(L(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}var V={name:`ToggleButton`,extends:I,inheritAttrs:!1,emits:[`change`],methods:{getPTOptions:function(e){return(e===`root`?this.ptmi:this.ptm)(e,{context:{active:this.active,disabled:this.disabled}})},onChange:function(e){!this.disabled&&!this.readonly&&(this.writeValue(!this.d_value,e),this.$emit(`change`,e))},onBlur:function(e){var t,n;(t=(n=this.formField).onBlur)==null||t.call(n,e)}},computed:{active:function(){return this.d_value===!0},hasLabel:function(){return g(this.onLabel)&&g(this.offLabel)},label:function(){return this.hasLabel?this.d_value?this.onLabel:this.offLabel:`\xA0`},dataP:function(){return y(R({checked:this.active,invalid:this.$invalid},this.size,this.size))}},directives:{ripple:C}},H=[`tabindex`,`disabled`,`aria-pressed`,`aria-label`,`aria-labelledby`,`data-p-checked`,`data-p-disabled`,`data-p`],U=[`data-p`];function W(t,n,r,a,o,s){var c=h(`ripple`);return p((S(),j(`button`,l({type:`button`,class:t.cx(`root`),tabindex:t.tabindex,disabled:t.disabled,"aria-pressed":t.d_value,onClick:n[0]||=function(){return s.onChange&&s.onChange.apply(s,arguments)},onBlur:n[1]||=function(){return s.onBlur&&s.onBlur.apply(s,arguments)}},s.getPTOptions(`root`),{"aria-label":t.ariaLabel,"aria-labelledby":t.ariaLabelledby,"data-p-checked":s.active,"data-p-disabled":t.disabled,"data-p":s.dataP}),[i(`span`,l({class:t.cx(`content`)},s.getPTOptions(`content`),{"data-p":s.dataP}),[v(t.$slots,`default`,{},function(){return[v(t.$slots,`icon`,{value:t.d_value,class:e(t.cx(`icon`))},function(){return[t.onIcon||t.offIcon?(S(),j(`span`,l({key:0,class:[t.cx(`icon`),t.d_value?t.onIcon:t.offIcon]},s.getPTOptions(`icon`)),null,16)):u(``,!0)]}),i(`span`,l({class:t.cx(`label`)},s.getPTOptions(`label`)),x(s.label),17)]})],16,U)],16,H)),[[c]])}V.render=W;var G=O.extend({name:`selectbutton`,style:`
    .p-selectbutton {
        display: inline-flex;
        user-select: none;
        vertical-align: bottom;
        outline-color: transparent;
        border-radius: dt('selectbutton.border.radius');
    }

    .p-selectbutton .p-togglebutton {
        border-radius: 0;
        border-width: 1px 1px 1px 0;
    }

    .p-selectbutton .p-togglebutton:focus-visible {
        position: relative;
        z-index: 1;
    }

    .p-selectbutton .p-togglebutton:first-child {
        border-inline-start-width: 1px;
        border-start-start-radius: dt('selectbutton.border.radius');
        border-end-start-radius: dt('selectbutton.border.radius');
    }

    .p-selectbutton .p-togglebutton:last-child {
        border-start-end-radius: dt('selectbutton.border.radius');
        border-end-end-radius: dt('selectbutton.border.radius');
    }

    .p-selectbutton.p-invalid {
        outline: 1px solid dt('selectbutton.invalid.border.color');
        outline-offset: 0;
    }

    .p-selectbutton-fluid {
        width: 100%;
    }
    
    .p-selectbutton-fluid .p-togglebutton {
        flex: 1 1 0;
    }
`,classes:{root:function(e){var t=e.props,n=e.instance;return[`p-selectbutton p-component`,{"p-invalid":n.$invalid,"p-selectbutton-fluid":t.fluid}]}}}),K={name:`BaseSelectButton`,extends:N,props:{options:Array,optionLabel:null,optionValue:null,optionDisabled:null,multiple:Boolean,allowEmpty:{type:Boolean,default:!0},dataKey:null,ariaLabelledby:{type:String,default:null},size:{type:String,default:null},fluid:{type:Boolean,default:null}},style:G,provide:function(){return{$pcSelectButton:this,$parentInstance:this}}};function q(e,t){var n=typeof Symbol<`u`&&e[Symbol.iterator]||e[`@@iterator`];if(!n){if(Array.isArray(e)||(n=X(e))||t){n&&(e=n);var r=0,i=function(){};return{s:i,n:function(){return r>=e.length?{done:!0}:{done:!1,value:e[r++]}},e:function(e){throw e},f:i}}throw TypeError(`Invalid attempt to iterate non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}var a,o=!0,s=!1;return{s:function(){n=n.call(e)},n:function(){var e=n.next();return o=e.done,e},e:function(e){s=!0,a=e},f:function(){try{o||n.return==null||n.return()}finally{if(s)throw a}}}}function J(e){return ae(e)||ie(e)||X(e)||Y()}function Y(){throw TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function X(e,t){if(e){if(typeof e==`string`)return Z(e,t);var n={}.toString.call(e).slice(8,-1);return n===`Object`&&e.constructor&&(n=e.constructor.name),n===`Map`||n===`Set`?Array.from(e):n===`Arguments`||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?Z(e,t):void 0}}function ie(e){if(typeof Symbol<`u`&&e[Symbol.iterator]!=null||e[`@@iterator`]!=null)return Array.from(e)}function ae(e){if(Array.isArray(e))return Z(e)}function Z(e,t){(t==null||t>e.length)&&(t=e.length);for(var n=0,r=Array(t);n<t;n++)r[n]=e[n];return r}var Q={name:`SelectButton`,extends:K,inheritAttrs:!1,emits:[`change`],methods:{getOptionLabel:function(e){return this.optionLabel?t(e,this.optionLabel):e},getOptionValue:function(e){return this.optionValue?t(e,this.optionValue):e},getOptionRenderKey:function(e){return this.dataKey?t(e,this.dataKey):this.getOptionLabel(e)},isOptionDisabled:function(e){return this.optionDisabled?t(e,this.optionDisabled):!1},isOptionReadonly:function(e){if(this.allowEmpty)return!1;var t=this.isSelected(e);return this.multiple?t&&this.d_value.length===1:t},onOptionSelect:function(e,t,n){var r=this;if(!(this.disabled||this.isOptionDisabled(t)||this.isOptionReadonly(t))){var i=this.isSelected(t),a=this.getOptionValue(t),o;if(this.multiple)if(i){if(o=this.d_value.filter(function(e){return!k(e,a,r.equalityKey)}),!this.allowEmpty&&o.length===0)return}else o=this.d_value?[].concat(J(this.d_value),[a]):[a];else{if(i&&!this.allowEmpty)return;o=i?null:a}this.writeValue(o,e),this.$emit(`change`,{originalEvent:e,value:o})}},isSelected:function(e){var t=!1,n=this.getOptionValue(e);if(this.multiple){if(this.d_value){var r=q(this.d_value),i;try{for(r.s();!(i=r.n()).done;){var a=i.value;if(k(a,n,this.equalityKey)){t=!0;break}}}catch(e){r.e(e)}finally{r.f()}}}else t=k(this.d_value,n,this.equalityKey);return t}},computed:{equalityKey:function(){return this.optionValue?null:this.dataKey},dataP:function(){return y({invalid:this.$invalid})}},directives:{ripple:C},components:{ToggleButton:V}},oe=[`aria-labelledby`,`data-p`];function se(e,t,n,r,s,c){var u=D(`ToggleButton`);return S(),j(`div`,l({class:e.cx(`root`),role:`group`,"aria-labelledby":e.ariaLabelledby},e.ptmi(`root`),{"data-p":c.dataP}),[(S(!0),j(o,null,m(e.options,function(t,n){return S(),a(u,{key:c.getOptionRenderKey(t),modelValue:c.isSelected(t),onLabel:c.getOptionLabel(t),offLabel:c.getOptionLabel(t),disabled:e.disabled||c.isOptionDisabled(t),unstyled:e.unstyled,size:e.size,readonly:c.isOptionReadonly(t),onChange:function(e){return c.onOptionSelect(e,t,n)},pt:e.ptm(`pcToggleButton`)},d({_:2},[e.$slots.option?{name:`default`,fn:A(function(){return[v(e.$slots,`option`,{option:t,index:n},function(){return[i(`span`,l({ref_for:!0},e.ptm(`pcToggleButton`).label),x(c.getOptionLabel(t)),17)]})]}),key:`0`}:void 0]),1032,[`modelValue`,`onLabel`,`offLabel`,`disabled`,`unstyled`,`size`,`readonly`,`onChange`,`pt`])}),128))],16,oe)}Q.render=se;function ce(){return te(`/plans/public`)}function le(e,t){return ee(`/subscription/upgrade`,{plan_id:e,billing_cycle:t})}var ue={class:`pricing-page`},de={class:`pricing-header`},fe={class:`pricing-title`},pe={class:`pricing-sub`},me={class:`cycle-toggle`},he={key:0,class:`save-badge`},ge={key:0,class:`loading-state`},_e={key:1,class:`plans-grid`},ve={key:0,class:`popular-badge`},ye={key:1,class:`current-badge`},be={class:`plan-head`},$={class:`plan-desc`},xe={class:`plan-price`},Se={class:`price-amount`},Ce={class:`price-period`},we={class:`plan-credits`},Te={class:`credits-amount`},Ee={class:`credits-label`},De={class:`plan-features`},Oe={class:`pricing-footer`},ke=T(r({__name:`PricingView`,setup(t){let{t:r}=_(),a=re(),l=ne();M({title:n(()=>r(`seo.pricingTitle`)),description:n(()=>r(`seo.pricingDescription`)),path:`/pricing`,jsonLd:{"@context":`https://schema.org`,"@type":`WebPage`,name:`Plans & Pricing - Klek AI`,url:`https://klek.studio/pricing`,description:`Choose the perfect Klek AI plan for your creative needs.`,isPartOf:{"@type":`WebSite`,name:`Klek AI`,url:`https://klek.studio`}}});let d=s(!0),p=s(null),h=s(`monthly`),g=s([]),v=n(()=>[{label:r(`client.monthly`),value:`monthly`},{label:r(`client.yearly`),value:`yearly`}]),y={free:`#64748b`,starter:`#0ea5e9`,pro:`#8b5cf6`,professional:`#8b5cf6`,enterprise:`#f59e0b`},C=n(()=>(g.value??[]).map(e=>({id:e.id,name:e.name,slug:e.slug,price:h.value===`yearly`?e.price_yearly:e.price_monthly,credits:h.value===`yearly`?e.credits_yearly:e.credits_monthly,currency:e.currency??`USD`,description:e.description??``,is_free:e.is_free,is_featured:e.is_featured,features:(e.features??[]).map(e=>e.name),tone:y[e.slug]??`#64748b`,isCurrent:l.isAuthenticated&&l.quota?.plan_slug===e.slug})));w(async()=>{try{g.value=(await ce()).data??[]}catch{g.value=[]}finally{d.value=!1}});async function T(e){if(!l.isAuthenticated){a.push({name:`login`});return}if(!(e.isCurrent||e.is_free)){p.value=e.id;try{await le(e.id,h.value),await l.refreshQuota()}catch{}finally{p.value=null}}}return(t,n)=>(S(),j(`div`,ue,[i(`div`,de,[i(`h1`,fe,x(c(r)(`client.pricingTitle`)),1),i(`p`,pe,x(c(r)(`client.pricingSub`)),1),i(`div`,me,[f(c(Q),{modelValue:h.value,"onUpdate:modelValue":n[0]||=e=>h.value=e,options:v.value,optionLabel:`label`,optionValue:`value`,allowEmpty:!1},null,8,[`modelValue`,`options`]),h.value===`yearly`?(S(),j(`span`,he,x(c(r)(`client.save20`)),1)):u(``,!0)])]),d.value?(S(),j(`div`,ge,[...n[1]||=[i(`i`,{class:`pi pi-spin pi-spinner`,style:{"font-size":`2rem`,color:`var(--text-muted)`}},null,-1)]])):(S(),j(`div`,_e,[(S(!0),j(o,null,m(C.value,t=>(S(),j(`article`,{key:t.slug,class:e([`plan-card`,{popular:t.is_featured,current:t.isCurrent}])},[t.is_featured?(S(),j(`div`,ve,[f(c(P),{value:c(r)(`client.mostPopular`),severity:`contrast`,class:`pop-tag`},null,8,[`value`])])):u(``,!0),t.isCurrent?(S(),j(`div`,ye,[f(c(P),{value:c(r)(`client.currentPlan`),severity:`success`,class:`pop-tag`},null,8,[`value`])])):u(``,!0),i(`div`,be,[i(`h3`,{class:`plan-name`,style:E({color:t.tone})},x(t.name),5),i(`p`,$,x(t.description),1)]),i(`div`,xe,[i(`span`,Se,`$`+x(t.price),1),i(`span`,Ce,`/ `+x(h.value===`yearly`?c(r)(`client.perYear`):c(r)(`client.perMonth`)),1)]),i(`div`,we,[i(`span`,Te,x(t.credits?.toLocaleString()),1),i(`span`,Ee,x(c(r)(`client.credits`)),1)]),i(`ul`,De,[(S(!0),j(o,null,m(t.features,(e,n)=>(S(),j(`li`,{key:n,class:`feature-item`},[i(`i`,{class:`pi pi-check-circle feature-check`,style:E({color:t.tone})},null,4),i(`span`,null,x(e),1)]))),128))]),f(c(b),{label:t.isCurrent?c(r)(`client.currentPlan`):t.is_free?c(r)(`client.startFree`):c(r)(`client.subscribe`),outlined:!t.is_featured,severity:t.is_featured?void 0:`secondary`,disabled:t.isCurrent||p.value!==null,loading:p.value===t.id,size:`small`,class:`plan-cta`,onClick:e=>T(t)},null,8,[`label`,`outlined`,`severity`,`disabled`,`loading`,`onClick`])],2))),128))])),i(`div`,Oe,[i(`p`,null,x(c(r)(`client.pricingFooter`)),1)])]))}}),[[`__scopeId`,`data-v-df5f083f`]]);export{ke as default};