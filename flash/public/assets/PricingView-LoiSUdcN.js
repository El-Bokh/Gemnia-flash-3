import{A as e,Cn as t,Ct as n,Gt as r,I as i,It as a,Jt as o,Kt as s,Mt as c,Pt as l,S as u,Xt as d,Yt as f,Zt as p,_n as m,a as h,b as g,bn as _,cn as v,dn as y,en as b,fn as ee,gn as x,h as S,k as C,l as te,ln as w,on as ne,qt as T,rn as E,tn as re,u as D,un as O,wn as k,xn as A,yn as j}from"./router-DuPm4qza.js";import{t as M}from"./baseeditableholder-Dvz279So.js";import{t as N}from"./tag-CiBreGhf.js";var P=i.extend({name:`togglebutton`,style:`
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
`,classes:{root:function(e){var t=e.instance,n=e.props;return[`p-togglebutton p-component`,{"p-togglebutton-checked":t.active,"p-invalid":t.$invalid,"p-togglebutton-fluid":n.fluid,"p-togglebutton-sm p-inputfield-sm":n.size===`small`,"p-togglebutton-lg p-inputfield-lg":n.size===`large`}]},content:`p-togglebutton-content`,icon:`p-togglebutton-icon`,label:`p-togglebutton-label`}}),F={name:`BaseToggleButton`,extends:M,props:{onIcon:String,offIcon:String,onLabel:{type:String,default:`Yes`},offLabel:{type:String,default:`No`},readonly:{type:Boolean,default:!1},tabindex:{type:Number,default:null},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null},size:{type:String,default:null},fluid:{type:Boolean,default:null}},style:P,provide:function(){return{$pcToggleButton:this,$parentInstance:this}}};function I(e){"@babel/helpers - typeof";return I=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},I(e)}function L(e,t,n){return(t=R(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function R(e){var t=z(e,`string`);return I(t)==`symbol`?t:t+``}function z(e,t){if(I(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if(I(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}var B={name:`ToggleButton`,extends:F,inheritAttrs:!1,emits:[`change`],methods:{getPTOptions:function(e){return(e===`root`?this.ptmi:this.ptm)(e,{context:{active:this.active,disabled:this.disabled}})},onChange:function(e){!this.disabled&&!this.readonly&&(this.writeValue(!this.d_value,e),this.$emit(`change`,e))},onBlur:function(e){var t,n;(t=(n=this.formField).onBlur)==null||t.call(n,e)}},computed:{active:function(){return this.d_value===!0},hasLabel:function(){return a(this.onLabel)&&a(this.offLabel)},label:function(){return this.hasLabel?this.d_value?this.onLabel:this.offLabel:`\xA0`},dataP:function(){return n(L({checked:this.active,invalid:this.$invalid},this.size,this.size))}},directives:{ripple:D}},V=[`tabindex`,`disabled`,`aria-pressed`,`aria-label`,`aria-labelledby`,`data-p-checked`,`data-p-disabled`,`data-p`],H=[`data-p`];function U(e,t,n,r,i,a){var o=ee(`ripple`);return m((v(),d(`button`,E({type:`button`,class:e.cx(`root`),tabindex:e.tabindex,disabled:e.disabled,"aria-pressed":e.d_value,onClick:t[0]||=function(){return a.onChange&&a.onChange.apply(a,arguments)},onBlur:t[1]||=function(){return a.onBlur&&a.onBlur.apply(a,arguments)}},a.getPTOptions(`root`),{"aria-label":e.ariaLabel,"aria-labelledby":e.ariaLabelledby,"data-p-checked":a.active,"data-p-disabled":e.disabled,"data-p":a.dataP}),[T(`span`,E({class:e.cx(`content`)},a.getPTOptions(`content`),{"data-p":a.dataP}),[O(e.$slots,`default`,{},function(){return[O(e.$slots,`icon`,{value:e.d_value,class:A(e.cx(`icon`))},function(){return[e.onIcon||e.offIcon?(v(),d(`span`,E({key:0,class:[e.cx(`icon`),e.d_value?e.onIcon:e.offIcon]},a.getPTOptions(`icon`)),null,16)):f(``,!0)]}),T(`span`,E({class:e.cx(`label`)},a.getPTOptions(`label`)),k(a.label),17)]})],16,H)],16,V)),[[o]])}B.render=U;var W=i.extend({name:`selectbutton`,style:`
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
`,classes:{root:function(e){var t=e.props,n=e.instance;return[`p-selectbutton p-component`,{"p-invalid":n.$invalid,"p-selectbutton-fluid":t.fluid}]}}}),G={name:`BaseSelectButton`,extends:M,props:{options:Array,optionLabel:null,optionValue:null,optionDisabled:null,multiple:Boolean,allowEmpty:{type:Boolean,default:!0},dataKey:null,ariaLabelledby:{type:String,default:null},size:{type:String,default:null},fluid:{type:Boolean,default:null}},style:W,provide:function(){return{$pcSelectButton:this,$parentInstance:this}}};function K(e,t){var n=typeof Symbol<`u`&&e[Symbol.iterator]||e[`@@iterator`];if(!n){if(Array.isArray(e)||(n=Y(e))||t){n&&(e=n);var r=0,i=function(){};return{s:i,n:function(){return r>=e.length?{done:!0}:{done:!1,value:e[r++]}},e:function(e){throw e},f:i}}throw TypeError(`Invalid attempt to iterate non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}var a,o=!0,s=!1;return{s:function(){n=n.call(e)},n:function(){var e=n.next();return o=e.done,e},e:function(e){s=!0,a=e},f:function(){try{o||n.return==null||n.return()}finally{if(s)throw a}}}}function q(e){return ie(e)||X(e)||Y(e)||J()}function J(){throw TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function Y(e,t){if(e){if(typeof e==`string`)return Z(e,t);var n={}.toString.call(e).slice(8,-1);return n===`Object`&&e.constructor&&(n=e.constructor.name),n===`Map`||n===`Set`?Array.from(e):n===`Arguments`||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?Z(e,t):void 0}}function X(e){if(typeof Symbol<`u`&&e[Symbol.iterator]!=null||e[`@@iterator`]!=null)return Array.from(e)}function ie(e){if(Array.isArray(e))return Z(e)}function Z(e,t){(t==null||t>e.length)&&(t=e.length);for(var n=0,r=Array(t);n<t;n++)r[n]=e[n];return r}var Q={name:`SelectButton`,extends:G,inheritAttrs:!1,emits:[`change`],methods:{getOptionLabel:function(e){return this.optionLabel?l(e,this.optionLabel):e},getOptionValue:function(e){return this.optionValue?l(e,this.optionValue):e},getOptionRenderKey:function(e){return this.dataKey?l(e,this.dataKey):this.getOptionLabel(e)},isOptionDisabled:function(e){return this.optionDisabled?l(e,this.optionDisabled):!1},isOptionReadonly:function(e){if(this.allowEmpty)return!1;var t=this.isSelected(e);return this.multiple?t&&this.d_value.length===1:t},onOptionSelect:function(e,t,n){var r=this;if(!(this.disabled||this.isOptionDisabled(t)||this.isOptionReadonly(t))){var i=this.isSelected(t),a=this.getOptionValue(t),o;if(this.multiple)if(i){if(o=this.d_value.filter(function(e){return!c(e,a,r.equalityKey)}),!this.allowEmpty&&o.length===0)return}else o=this.d_value?[].concat(q(this.d_value),[a]):[a];else{if(i&&!this.allowEmpty)return;o=i?null:a}this.writeValue(o,e),this.$emit(`change`,{originalEvent:e,value:o})}},isSelected:function(e){var t=!1,n=this.getOptionValue(e);if(this.multiple){if(this.d_value){var r=K(this.d_value),i;try{for(r.s();!(i=r.n()).done;){var a=i.value;if(c(a,n,this.equalityKey)){t=!0;break}}}catch(e){r.e(e)}finally{r.f()}}}else t=c(this.d_value,n,this.equalityKey);return t}},computed:{equalityKey:function(){return this.optionValue?null:this.dataKey},dataP:function(){return n({invalid:this.$invalid})}},directives:{ripple:D},components:{ToggleButton:B}},ae=[`aria-labelledby`,`data-p`];function oe(e,t,n,i,a,s){var c=y(`ToggleButton`);return v(),d(`div`,E({class:e.cx(`root`),role:`group`,"aria-labelledby":e.ariaLabelledby},e.ptmi(`root`),{"data-p":s.dataP}),[(v(!0),d(r,null,w(e.options,function(t,n){return v(),o(c,{key:s.getOptionRenderKey(t),modelValue:s.isSelected(t),onLabel:s.getOptionLabel(t),offLabel:s.getOptionLabel(t),disabled:e.disabled||s.isOptionDisabled(t),unstyled:e.unstyled,size:e.size,readonly:s.isOptionReadonly(t),onChange:function(e){return s.onOptionSelect(e,t,n)},pt:e.ptm(`pcToggleButton`)},p({_:2},[e.$slots.option?{name:`default`,fn:x(function(){return[O(e.$slots,`option`,{option:t,index:n},function(){return[T(`span`,E({ref_for:!0},e.ptm(`pcToggleButton`).label),k(s.getOptionLabel(t)),17)]})]}),key:`0`}:void 0]),1032,[`modelValue`,`onLabel`,`offLabel`,`disabled`,`unstyled`,`size`,`readonly`,`onChange`,`pt`])}),128))],16,ae)}Q.render=oe;function se(){return g(`/plans/public`)}function ce(e,t){return u(`/subscription/upgrade`,{plan_id:e,billing_cycle:t})}var le={class:`pricing-page`},ue={class:`pricing-header`},de={class:`pricing-title`},fe={class:`pricing-sub`},pe={class:`cycle-toggle`},me={key:0,class:`save-badge`},he={key:0,class:`loading-state`},ge={key:1,class:`plans-grid`},_e={key:0,class:`popular-badge`},ve={key:1,class:`current-badge`},ye={class:`plan-head`},be={class:`plan-desc`},xe={class:`plan-price`},$={class:`price-amount`},Se={class:`price-period`},Ce={class:`plan-credits`},we={class:`credits-amount`},Te={class:`credits-label`},Ee={class:`plan-features`},De={class:`pricing-footer`},Oe=h(re({__name:`PricingView`,setup(n){let{t:i}=C(),a=e(),o=S(),c=j(!0),l=j(null),u=j(`monthly`),p=j([]),m=s(()=>[{label:i(`client.monthly`),value:`monthly`},{label:i(`client.yearly`),value:`yearly`}]),h={free:`#64748b`,starter:`#0ea5e9`,pro:`#8b5cf6`,professional:`#8b5cf6`,enterprise:`#f59e0b`},g=s(()=>(p.value??[]).map(e=>({id:e.id,name:e.name,slug:e.slug,price:u.value===`yearly`?e.price_yearly:e.price_monthly,credits:u.value===`yearly`?e.credits_yearly:e.credits_monthly,currency:e.currency??`USD`,description:e.description??``,is_free:e.is_free,is_featured:e.is_featured,features:(e.features??[]).map(e=>e.name),tone:h[e.slug]??`#64748b`,isCurrent:o.isAuthenticated&&o.quota?.plan_slug===e.slug})));ne(async()=>{try{p.value=(await se()).data??[]}catch{p.value=[]}finally{c.value=!1}});async function y(e){if(!o.isAuthenticated){a.push({name:`login`});return}if(!(e.isCurrent||e.is_free)){l.value=e.id;try{await ce(e.id,u.value),await o.refreshQuota()}catch{}finally{l.value=null}}}return(e,n)=>(v(),d(`div`,le,[T(`div`,ue,[T(`h1`,de,k(_(i)(`client.pricingTitle`)),1),T(`p`,fe,k(_(i)(`client.pricingSub`)),1),T(`div`,pe,[b(_(Q),{modelValue:u.value,"onUpdate:modelValue":n[0]||=e=>u.value=e,options:m.value,optionLabel:`label`,optionValue:`value`,allowEmpty:!1},null,8,[`modelValue`,`options`]),u.value===`yearly`?(v(),d(`span`,me,k(_(i)(`client.save20`)),1)):f(``,!0)])]),c.value?(v(),d(`div`,he,[...n[1]||=[T(`i`,{class:`pi pi-spin pi-spinner`,style:{"font-size":`2rem`,color:`var(--text-muted)`}},null,-1)]])):(v(),d(`div`,ge,[(v(!0),d(r,null,w(g.value,e=>(v(),d(`article`,{key:e.slug,class:A([`plan-card`,{popular:e.is_featured,current:e.isCurrent}])},[e.is_featured?(v(),d(`div`,_e,[b(_(N),{value:_(i)(`client.mostPopular`),severity:`contrast`,class:`pop-tag`},null,8,[`value`])])):f(``,!0),e.isCurrent?(v(),d(`div`,ve,[b(_(N),{value:_(i)(`client.currentPlan`),severity:`success`,class:`pop-tag`},null,8,[`value`])])):f(``,!0),T(`div`,ye,[T(`h3`,{class:`plan-name`,style:t({color:e.tone})},k(e.name),5),T(`p`,be,k(e.description),1)]),T(`div`,xe,[T(`span`,$,`$`+k(e.price),1),T(`span`,Se,`/ `+k(u.value===`yearly`?_(i)(`client.perYear`):_(i)(`client.perMonth`)),1)]),T(`div`,Ce,[T(`span`,we,k(e.credits?.toLocaleString()),1),T(`span`,Te,k(_(i)(`client.credits`)),1)]),T(`ul`,Ee,[(v(!0),d(r,null,w(e.features,(n,r)=>(v(),d(`li`,{key:r,class:`feature-item`},[T(`i`,{class:`pi pi-check-circle feature-check`,style:t({color:e.tone})},null,4),T(`span`,null,k(n),1)]))),128))]),b(_(te),{label:e.isCurrent?_(i)(`client.currentPlan`):e.is_free?_(i)(`client.startFree`):_(i)(`client.subscribe`),outlined:!e.is_featured,severity:e.is_featured?void 0:`secondary`,disabled:e.isCurrent||l.value!==null,loading:l.value===e.id,size:`small`,class:`plan-cta`,onClick:t=>y(e)},null,8,[`label`,`outlined`,`severity`,`disabled`,`loading`,`onClick`])],2))),128))])),T(`div`,De,[T(`p`,null,k(_(i)(`client.pricingFooter`)),1)])]))}}),[[`__scopeId`,`data-v-7a4d679f`]]);export{Oe as default};