import $ from 'jquery';
import axios from 'axios';
import _ from 'lodash';

declare global {
  interface Window {
    $: typeof $;
    jQuery: typeof $;
    axios: typeof axios;
    _: typeof _;
  }
}

window.$ = window.jQuery = $;
window.axios = axios;
window._ = _;