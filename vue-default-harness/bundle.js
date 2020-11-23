/* @meta('js-entrypoint', ['output' => 'core.js']); */
/* @meta('load-javascripts', ['url' => '/core.js']); */
/* @meta('load-stylesheets', ['url' => '/core.css']); */

import 'regenerator-runtime/runtime'

import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Vue from 'vue/dist/vue.common';

window.Vue = Vue;

var vuecf = require("vue-blocks/vue-component-framework");

vuecf.loadModules();
vuecf.loadVueComponents();

import VueRouter from "vue-router";

var routes = vuecf.collectRoutes();
var router = new VueRouter({
  routes: routes
});

Vue.use(VueRouter);

/* @inserts javascript-functions */
// @snippet 692dcc-61ac6c-80bdd3-c9e2ae
	// one weakness: insertTab destroys your undo history...
	var tab = "\t";
	function insertTab(o, e){
	    var kC = e.which;
	
	    if (kC == 9 && !e.shiftKey && !e.ctrlKey && !e.altKey && !e.mId) {         
	        var oS = o.scrollTop;
	        if (o.setSelectionRange) {
	            var sS = o.selectionStart;
	            var sE = o.selectionEnd;
	            o.value = o.value.substring(0, sS) + tab + o.value.substr(sE);
	            o.setSelectionRange(sS + tab.length, sS + tab.length);
	            o.focus();
	        }
	        else if (o.createTextRange) {
	            document.selection.createRange().text = tab;
	            e.returnValue = false;
	        }
	        o.scrollTop = oS;
	        e.mId = true;
	        if (e.preventDefault) {  e.preventDefault(); }
	        return false;
	    }  return true; 
	}
	
	Vue.directive('tab', (el) => {
	    el.addEventListener('keydown', event => insertTab(el, event));
	});
// @endsnippet

// @snippet de4011-d9640c-6f208e-d680ce
	/**
	 * @common de4011-d9640c-6f208e-d680ce/link-to-storage.js
	 * Links vue component variables to either localStorage or sessionStorage.
	 * 
	 * Usage inside your vue components;
	 * 
	 * yourComponent = {
	 *      mounted() {
	 *          this.link('myVariable').to.localStorage('someLocalStorageKey');
	 *          this.link('otherVar').to.sessionStorage();
	 *      }
	 * }
	 * 
	 */
	
	 Vue.prototype.link = function link(key) {
	    
	    var linkToStorage = (storage, storageKey) => {
	        if (!window.APP_NAME) {
	            console.error('Please set window.APP_NAME in this unit.');
	            var APP_NAME = 'UnknownApp';
	        }
	        
	
	        var storageKey = [
	            APP_NAME || '',
	            this.$options._componentTag,
	            storageKey
	        ].filter(Boolean).join('.');
	
	        //console.log("Read " + storageKey);
	
	        if (storage[storageKey]) {
	            try { 
	                this.$set(this, key, JSON.parse(storage[storageKey] || 'null'))
	                //console.log("Sets " + key + " to " + storageKey);
	            } catch (ignore) {
	                //console.log("Error reading " + storageKey, ignore);
	            }
	        }
	
	        this.$watch(key, function(value) {
	            //console.log("Updates " + storageKey);
	
	            // @fixme: Throttle this function
	            storage[storageKey]=JSON.stringify(value);
	        }, {deep:true});
	    };
	
	    return {
	        to: {
	            sessionStorage: (storageKey) => {
	                return linkToStorage(sessionStorage, storageKey);
	            },
	            localStorage: (storageKey) => {
	                return linkToStorage(localStorage, storageKey);
	            }
	        }
	    }
	};
	
// @endsnippet
// @snippet 5f8864-960ae0-59fb0f-2d4bff
	/**
	 * Vue ctrl-s handler
	 * 
	 * Usage: <main v-ctrl-s="submit">
	 * 
	 * @author Joshua Angnoe
	 * @package BOS - VueBase
	 * @common 5f8864-960ae0-59fb0f-2d4bff/ctrl-s.js
	 */
	
	var autoSaveHandlers = [];
	
	document.addEventListener('keydown', event => {
	    if (event.ctrlKey && event.which === 83) {
	        event.preventDefault();
	        console.log(autoSaveHandlers);
	        
	        var p = event.target.parentNode;
	        while(p && p.parentNode) {
	            if (p.autoSave) {
	                return p.autoSave(event);
	            }
	            p = p.parentNode;
	        }
	
	        autoSaveHandlers[autoSaveHandlers.length-1]();
	    }
	});
	
	Vue.directive('ctrl-s', {
	    bind(el, attrs) {
	        console.log(attrs.value);
	
	        el.autoSave = () => {
	            attrs.value();
	        }
	
	        autoSaveHandlers.push( () => {
	            attrs.value();
	        });
	    },
	    unbind(el) {
	        autoSaveHandlers.pop();
	    }
	});
	
// @endsnippet
/* @snippet 561cad-fa2976-b27553-d60a7a */
		  // Popup error 
		  window.axios.interceptors.response.use(
		  function(response) {
		    // console.log(response, 'response intercepted');
		    
		    return response;
		  },
		  function(error) {
		    if (error.response.status === 500) {
		      popupError(error);
		    }
		    
		    return Promise.reject(error);
		  }
		);
		
		function popupError(error) {
		  // console.log(error.response);
		  window.dialog.launch({
		    width: 800,
		    height: 800,
		    centered: true,
		    modal: true,
		    title: '<span style="color:red;">Server error occured</span>',
		    component: {
		      data: error.response,
	          template: `<div>
	            <div v-if="data">
	                <pre style="white-space: pre-wrap;">{{data}}</pre>
	            </div>
	            <div v-else>
	                <pre style="white-space: pre-wrap;">{{$data}}</pre>
	            </div>
	          </div>`
	        }
		  });
		}
/* @endsnippet */

/* @snippet fcb201-2ff8ae-94c4e6-e8f2b9 */

	Vue.directive('autoheight', (el) => {
	    el.wrap = 'off';
	
	    var resizeFn = () => {
	        var extra = el.scrollWidth > el.offsetWidth;
	        el.style.height = (el.scrollHeight - 10) + 'px';
	        el.style.height = (el.scrollHeight + (extra ? 25 : 5)) + 'px';
	    };
	    var timeout;
	    var resizeFnDebounce = () => {
	        clearTimeout(timeout);
	        timeout = setTimeout(resizeFn, 50);
	    };
	    el.addEventListener('keyup', resizeFnDebounce)
	    resizeFn();
	    setTimeout(resizeFn, 50);
	});
/* @endsnippet */

/* @snippet 36b43b-5240e6-f5f63e-e03e42 */

	window.api = new Proxy({}, {
	    get(obj, method) {
	        return function(...args) {
	            return axios.post('?rpc=' + method, {
	                rpc: [method, args]
	            }).then(r => r.data)
	        }
	    }
	});
/* @endsnippet */

/* @snippet f4f7c0-bb683c-28d08f-36f061 */

	function wait(n) {
		return new Promise(resolve => setTimeout(resolve, n));
	}
	window.wait = wait;
/* @endsnippet */

/* @snippet fc7904-60d727-743469-1029a6-loader */
	import UiSuggest from './components/ui-suggest.vue';
	Vue.component('ui-suggest', UiSuggest);
	
/* @endsnippet */

/* @snippet e5da0b-156d5c-494533-b53424-loader */
	var Toast = require("./components/toast/index");
	require("./components/toast/toast.css");
	Toast.exposeAs("toast");
	
/* @endsnippet */

/* @snippet e11e3d-98d579-b5c5c1-28784a-loader */
	require('./components/dialog/index')
	require('./components/dialog/dialog.css')
	
/* @endsnippet */

/* @endinserts */

Vue.prototype.api = window.api;


function startApp() {
    const app = new Vue({
      el: "app",
      router
    });
}

// This must be come last.
document.addEventListener('DOMContentLoaded', startApp);



