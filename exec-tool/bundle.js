import VueCodemirror from 'vue-codemirror'

import 'codemirror/lib/codemirror.css'
import 'codemirror/mode/javascript/javascript.js'
import 'codemirror/mode/htmlmixed/htmlmixed.js'
import 'codemirror/mode/php/php.js'
import 'codemirror/mode/css/css.js'
import 'codemirror/mode/sql/sql.js'
import 'codemirror/mode/markdown/markdown.js'
import 'codemirror/addon/comment/comment.js'
import 'codemirror/addon/edit/matchbrackets.js'
import 'codemirror/addon/display/autorefresh.js'

Vue.use(VueCodemirror, {
    events: ['focus'] 
});
