import{g as e}from"./auth-DDCY3VWd.js";import{A as t,F as n,I as r,J as i,L as a,Mt as o,N as s,R as c,T as l,Y as u,_ as d,c as f,d as p,f as m,i as h,it as g,jt as _,kt as v,l as y,m as b,t as x,u as S,ut as C,v as w}from"./_plugin-vue_export-helper-D2F7RcU9.js";import{bt as T,ht as E,i as D,vt as O}from"./basedirective-DSF8gHrX.js";import{n as k,s as A,t as ee}from"./button-UxAMddMY.js";import{n as te}from"./vue-i18n-core-DCFtTscT.js";import{l as j,s as M,t as ne}from"./index-2lBNFyN0.js";import{t as re}from"./layout-DsmwhQyD.js";import{t as ie}from"./useSeo-Dp7k7Vma.js";import{t as N}from"./baseeditableholder-THcxf570.js";import{t as P}from"./tag-BeWwqQES.js";var F=D.extend({name:`togglebutton`,style:`
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
`,classes:{root:function(e){var t=e.instance,n=e.props;return[`p-togglebutton p-component`,{"p-togglebutton-checked":t.active,"p-invalid":t.$invalid,"p-togglebutton-fluid":n.fluid,"p-togglebutton-sm p-inputfield-sm":n.size===`small`,"p-togglebutton-lg p-inputfield-lg":n.size===`large`}]},content:`p-togglebutton-content`,icon:`p-togglebutton-icon`,label:`p-togglebutton-label`}}),I={name:`BaseToggleButton`,extends:N,props:{onIcon:String,offIcon:String,onLabel:{type:String,default:`Yes`},offLabel:{type:String,default:`No`},readonly:{type:Boolean,default:!1},tabindex:{type:Number,default:null},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null},size:{type:String,default:null},fluid:{type:Boolean,default:null}},style:F,provide:function(){return{$pcToggleButton:this,$parentInstance:this}}};function L(e){"@babel/helpers - typeof";return L=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},L(e)}function R(e,t,n){return(t=z(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function z(e){var t=B(e,`string`);return L(t)==`symbol`?t:t+``}function B(e,t){if(L(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if(L(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}var V={name:`ToggleButton`,extends:I,inheritAttrs:!1,emits:[`change`],methods:{getPTOptions:function(e){return(e===`root`?this.ptmi:this.ptm)(e,{context:{active:this.active,disabled:this.disabled}})},onChange:function(e){!this.disabled&&!this.readonly&&(this.writeValue(!this.d_value,e),this.$emit(`change`,e))},onBlur:function(e){var t,n;(t=(n=this.formField).onBlur)==null||t.call(n,e)}},computed:{active:function(){return this.d_value===!0},hasLabel:function(){return T(this.onLabel)&&T(this.offLabel)},label:function(){return this.hasLabel?this.d_value?this.onLabel:this.offLabel:`\xA0`},dataP:function(){return A(R({checked:this.active,invalid:this.$invalid},this.size,this.size))}},directives:{ripple:k}},H=[`tabindex`,`disabled`,`aria-pressed`,`aria-label`,`aria-labelledby`,`data-p-checked`,`data-p-disabled`,`data-p`],U=[`data-p`];function W(e,t,n,i,a,d){var f=c(`ripple`);return u((s(),m(`button`,l({type:`button`,class:e.cx(`root`),tabindex:e.tabindex,disabled:e.disabled,"aria-pressed":e.d_value,onClick:t[0]||=function(){return d.onChange&&d.onChange.apply(d,arguments)},onBlur:t[1]||=function(){return d.onBlur&&d.onBlur.apply(d,arguments)}},d.getPTOptions(`root`),{"aria-label":e.ariaLabel,"aria-labelledby":e.ariaLabelledby,"data-p-checked":d.active,"data-p-disabled":e.disabled,"data-p":d.dataP}),[y(`span`,l({class:e.cx(`content`)},d.getPTOptions(`content`),{"data-p":d.dataP}),[r(e.$slots,`default`,{},function(){return[r(e.$slots,`icon`,{value:e.d_value,class:v(e.cx(`icon`))},function(){return[e.onIcon||e.offIcon?(s(),m(`span`,l({key:0,class:[e.cx(`icon`),e.d_value?e.onIcon:e.offIcon]},d.getPTOptions(`icon`)),null,16)):p(``,!0)]}),y(`span`,l({class:e.cx(`label`)},d.getPTOptions(`label`)),o(d.label),17)]})],16,U)],16,H)),[[f]])}V.render=W;var G=D.extend({name:`selectbutton`,style:`
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
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}var a,o=!0,s=!1;return{s:function(){n=n.call(e)},n:function(){var e=n.next();return o=e.done,e},e:function(e){s=!0,a=e},f:function(){try{o||n.return==null||n.return()}finally{if(s)throw a}}}}function J(e){return oe(e)||ae(e)||X(e)||Y()}function Y(){throw TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function X(e,t){if(e){if(typeof e==`string`)return Z(e,t);var n={}.toString.call(e).slice(8,-1);return n===`Object`&&e.constructor&&(n=e.constructor.name),n===`Map`||n===`Set`?Array.from(e):n===`Arguments`||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?Z(e,t):void 0}}function ae(e){if(typeof Symbol<`u`&&e[Symbol.iterator]!=null||e[`@@iterator`]!=null)return Array.from(e)}function oe(e){if(Array.isArray(e))return Z(e)}function Z(e,t){(t==null||t>e.length)&&(t=e.length);for(var n=0,r=Array(t);n<t;n++)r[n]=e[n];return r}var Q={name:`SelectButton`,extends:K,inheritAttrs:!1,emits:[`change`],methods:{getOptionLabel:function(e){return this.optionLabel?O(e,this.optionLabel):e},getOptionValue:function(e){return this.optionValue?O(e,this.optionValue):e},getOptionRenderKey:function(e){return this.dataKey?O(e,this.dataKey):this.getOptionLabel(e)},isOptionDisabled:function(e){return this.optionDisabled?O(e,this.optionDisabled):!1},isOptionReadonly:function(e){if(this.allowEmpty)return!1;var t=this.isSelected(e);return this.multiple?t&&this.d_value.length===1:t},onOptionSelect:function(e,t,n){var r=this;if(!(this.disabled||this.isOptionDisabled(t)||this.isOptionReadonly(t))){var i=this.isSelected(t),a=this.getOptionValue(t),o;if(this.multiple)if(i){if(o=this.d_value.filter(function(e){return!E(e,a,r.equalityKey)}),!this.allowEmpty&&o.length===0)return}else o=this.d_value?[].concat(J(this.d_value),[a]):[a];else{if(i&&!this.allowEmpty)return;o=i?null:a}this.writeValue(o,e),this.$emit(`change`,{originalEvent:e,value:o})}},isSelected:function(e){var t=!1,n=this.getOptionValue(e);if(this.multiple){if(this.d_value){var r=q(this.d_value),i;try{for(r.s();!(i=r.n()).done;){var a=i.value;if(E(a,n,this.equalityKey)){t=!0;break}}}catch(e){r.e(e)}finally{r.f()}}}else t=E(this.d_value,n,this.equalityKey);return t}},computed:{equalityKey:function(){return this.optionValue?null:this.dataKey},dataP:function(){return A({invalid:this.$invalid})}},directives:{ripple:k},components:{ToggleButton:V}},se=[`aria-labelledby`,`data-p`];function ce(e,t,c,u,d,f){var p=a(`ToggleButton`);return s(),m(`div`,l({class:e.cx(`root`),role:`group`,"aria-labelledby":e.ariaLabelledby},e.ptmi(`root`),{"data-p":f.dataP}),[(s(!0),m(h,null,n(e.options,function(t,n){return s(),S(p,{key:f.getOptionRenderKey(t),modelValue:f.isSelected(t),onLabel:f.getOptionLabel(t),offLabel:f.getOptionLabel(t),disabled:e.disabled||f.isOptionDisabled(t),unstyled:e.unstyled,size:e.size,readonly:f.isOptionReadonly(t),onChange:function(e){return f.onOptionSelect(e,t,n)},pt:e.ptm(`pcToggleButton`)},b({_:2},[e.$slots.option?{name:`default`,fn:i(function(){return[r(e.$slots,`option`,{option:t,index:n},function(){return[y(`span`,l({ref_for:!0},e.ptm(`pcToggleButton`).label),o(f.getOptionLabel(t)),17)]})]}),key:`0`}:void 0]),1032,[`modelValue`,`onLabel`,`offLabel`,`disabled`,`unstyled`,`size`,`readonly`,`onChange`,`pt`])}),128))],16,se)}Q.render=ce;function le(){return M(`/plans/public`)}function ue(e,t){return j(`/subscription/upgrade`,{plan_id:e,billing_cycle:t})}var de={class:`pricing-header`},fe={class:`pricing-title`},pe={class:`pricing-sub`},me={class:`cycle-toggle`},he={key:0,class:`save-badge`},ge={key:0,class:`loading-state`},_e={key:1,class:`plans-grid`},ve={key:0,class:`popular-badge`},ye={key:1,class:`current-badge`},be={class:`plan-head`},$={class:`plan-desc`},xe={class:`plan-price`},Se={class:`price-amount`},Ce={class:`price-period`},we={class:`plan-credits`},Te={class:`credits-amount`},Ee={class:`credits-label`},De={class:`plan-features`},Oe=x(w({__name:`PricingView`,setup(r){let{t:i}=te(),a=e(),c=ne(),l=re();ie({title:f(()=>i(`seo.pricingTitle`)),description:f(()=>i(`seo.pricingDescription`)),path:`/pricing`,jsonLd:{"@context":`https://schema.org`,"@type":`WebPage`,name:`Plans & Pricing - Klek AI`,url:`https://klek.studio/pricing`,description:`Choose the perfect Klek AI plan for your creative needs.`,isPartOf:{"@type":`WebSite`,name:`Klek AI`,url:`https://klek.studio`}}});let u=g(!0),b=g(null),x=g(`monthly`),S=g([]),w=f(()=>[{label:i(`client.monthly`),value:`monthly`},{label:i(`client.yearly`),value:`yearly`}]),T={free:`#64748b`,starter:`#0ea5e9`,pro:`#8b5cf6`,professional:`#8b5cf6`,enterprise:`#f59e0b`},E=f(()=>(S.value??[]).map(e=>({id:e.id,name:e.name,slug:e.slug,price:x.value===`yearly`?e.price_yearly:e.price_monthly,credits:x.value===`yearly`?e.credits_yearly:e.credits_monthly,currency:e.currency??`USD`,description:e.description??``,is_free:e.is_free,is_featured:e.is_featured,features:(e.features??[]).map(e=>e.name),tone:T[e.slug]??`#64748b`,isCurrent:c.isAuthenticated&&c.quota?.plan_slug===e.slug})));t(async()=>{try{S.value=(await le()).data??[]}catch{S.value=[]}finally{u.value=!1}});async function D(e){if(!c.isAuthenticated){a.push({name:`login`});return}if(!(e.isCurrent||e.is_free)){b.value=e.id;try{await ue(e.id,x.value),await c.refreshQuota()}catch{}finally{b.value=null}}}return(e,t)=>(s(),m(`div`,{class:v([`pricing-page`,{"pricing-page-dark":C(l).darkMode}])},[y(`div`,de,[y(`h1`,fe,o(C(i)(`client.pricingTitle`)),1),y(`p`,pe,o(C(i)(`client.pricingSub`)),1),y(`div`,me,[d(C(Q),{modelValue:x.value,"onUpdate:modelValue":t[0]||=e=>x.value=e,options:w.value,optionLabel:`label`,optionValue:`value`,allowEmpty:!1},null,8,[`modelValue`,`options`]),x.value===`yearly`?(s(),m(`span`,he,o(C(i)(`client.save20`)),1)):p(``,!0)])]),u.value?(s(),m(`div`,ge,[...t[1]||=[y(`i`,{class:`pi pi-spin pi-spinner`,style:{"font-size":`2rem`,color:`var(--text-muted)`}},null,-1)]])):(s(),m(`div`,_e,[(s(!0),m(h,null,n(E.value,e=>(s(),m(`article`,{key:e.slug,class:v([`plan-card`,{popular:e.is_featured,current:e.isCurrent}])},[e.is_featured?(s(),m(`div`,ve,[d(C(P),{value:C(i)(`client.mostPopular`),severity:`contrast`,class:`pop-tag`},null,8,[`value`])])):p(``,!0),e.isCurrent?(s(),m(`div`,ye,[d(C(P),{value:C(i)(`client.currentPlan`),severity:`success`,class:`pop-tag`},null,8,[`value`])])):p(``,!0),y(`div`,be,[y(`h3`,{class:`plan-name`,style:_({color:e.tone})},o(e.name),5),y(`p`,$,o(e.description),1)]),y(`div`,xe,[y(`span`,Se,`$`+o(e.price),1),y(`span`,Ce,`/ `+o(x.value===`yearly`?C(i)(`client.perYear`):C(i)(`client.perMonth`)),1)]),y(`div`,we,[y(`span`,Te,o(e.credits?.toLocaleString()),1),y(`span`,Ee,o(C(i)(`client.credits`)),1)]),y(`ul`,De,[(s(!0),m(h,null,n(e.features,(t,n)=>(s(),m(`li`,{key:n,class:`feature-item`},[y(`i`,{class:`pi pi-check-circle feature-check`,style:_({color:e.tone})},null,4),y(`span`,null,o(t),1)]))),128))]),d(C(ee),{label:e.isCurrent?C(i)(`client.currentPlan`):e.is_free?C(i)(`client.startFree`):C(i)(`client.subscribe`),outlined:!e.is_featured,severity:e.is_featured?void 0:`secondary`,disabled:e.isCurrent||b.value!==null,loading:b.value===e.id,size:`small`,class:`plan-cta`,onClick:t=>D(e)},null,8,[`label`,`outlined`,`severity`,`disabled`,`loading`,`onClick`])],2))),128))]))],2))}}),[[`__scopeId`,`data-v-e3623034`]]);export{Oe as default};