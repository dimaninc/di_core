/**
 * Created by dimaninc on 16.06.2015.
 */

var is_ie = document.all ? true : false,
    agt = navigator.userAgent.toLowerCase(),
    is_major = parseInt(navigator.appVersion),
    is_minor = parseFloat(navigator.appVersion),
    is_gecko = (agt.indexOf('gecko') != -1),
    is_ie4up = ((agt.indexOf('msie') != -1) && (agt.indexOf('opera') == -1) && (is_major >= 4)),
    is_opera = (agt.indexOf('opera') != -1),
    is_chrome = agt.toLowerCase().indexOf('chrome') > -1,
    is_safari = Object.prototype.toString.call(window.HTMLElement).indexOf('Constructor') > 0,
    is_mobile = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/.test(agt),
    is_android = /android/.test(agt) && !/opera|chrome|firefox|dolphin/.test(agt),
    is_ios = /iphone|ipad|ipod/.test(agt),
    is_ipad = agt.match(/ipad/i) != null,
    is_iphone = agt.match(/iphone/i) != null,
    is_phone = is_mobile && !is_ipad;

if (is_ie4up) {
    document.execCommand('BackgroundImageCache', false, true);
}

/** @deprecated */
function _ge(eid) {
    return document.getElementById(eid);
}

function getLeft(o) {
    return _get_left(o);
}

function getTop(o) {
    return _get_top(o);
}

function _get_left(o) {
    var x = o.offsetLeft;
    while (o = o.offsetParent) x += o.offsetLeft;
    return x;
}

function _get_top(o) {
    var y = o.offsetTop;
    while (o = o.offsetParent) y += o.offsetTop;
    return y;
}

// escape function
var escape_trans = [];
for (var i = 0x410; i <= 0x44F; i++) escape_trans[i] = i - 0x350; // А-Яа-я
escape_trans[0x401] = 0xA8;    // Ё
escape_trans[0x451] = 0xB8;    // ё

var escapeOrig = window.escape;

window.escape = function(str) {
    var ret = [];
    var str1 = str.toString();

    for (var i = 0; i < str1.length; i++) {
        var n = str1.charCodeAt(i);
        if (typeof escape_trans[n] != 'undefined') n = escape_trans[n];
        if (n <= 0xFF) ret.push(n);
    }
    return escapeOrig(String.fromCharCode.apply(null, ret)).replace('+', '%2B');
};
//

// unescape function
var untrans = [];
for (var i = 0x44F; i >= 0x410; i--) untrans[i - 0x350] = i; // А-Яа-я
untrans[0xA8] = 0x401;    // Ё
untrans[0xB8] = 0x451;    // ё

var unescapeOrig = window.unescape;

window.unescape = function(str) {
    var str = unescapeOrig(str);
    var ret = [];

    for (var i = 0; i < str.length; i++) {
        var n = str.charCodeAt(i);
        if (typeof untrans[n] != 'undefined')
            n = untrans[n];
        if (n <= untrans[0xFF])
            ret.push(n);
    }

    return String.fromCharCode.apply(null, ret);
};
//

// kill_endings
var disearch_endings_ar = [
    'ть',
    'ина', 'ин', // фамилии
    'ова', 'ева', 'ёва',
    'ами', 'ыми', 'ими', 'оми',
    'ой', 'ей', 'ай', 'ый', 'яй',
    'ая', 'яя',
    'яю', 'аю', 'ою',
    'ое', 'ее', 'ие', 'ия', 'ые',
    'их', 'ых', 'ах',
    'ов', 'ев', 'ёв',
    'ья', 'ье', 'ьё', 'ью',
    'ам', 'ым', 'им', 'ом',
    'че',
    'ь', 'а', 'о', 'и', 'ы', 'е', 'э', 'я', 'ю',
    'л', // для прошедшего времени
];
var disearch_min_word_length = 3;

function str_kill_ending(s) {
    var str_ar = s.split(/[\s]+/);
    var x, item;

    for (var j = 0; j < str_ar.length; j++) {
        item = str_ar[j];

        for (var i = 0; i < disearch_endings_ar.length; i++) {
            x = item.length - disearch_endings_ar[i].length;

            if (
                item.length > disearch_min_word_length &&
                disearch_endings_ar[i] == item.substr(x, item.length)
            ) {
                item = item.substr(0, x);

                break;
            }
        }

        str_ar[j] = item;
    }

    return str_ar.join(' ');
}

//

var __correct_latin_symbols_regexp = new RegExp('^[a-z0-9-_.]+$', 'i');
var __correct_digits_regexp = new RegExp('^[0-9.,]+$', 'i');
var __correct_email_regexp = new RegExp('^[0-9a-z]([-_.]*[0-9a-z])*@[0-9a-z]([-._]*[0-9a-z])*[.]{1}[a-z]{2,4}$', 'i');

function check_correct_latin_symbols(s) {
    return __correct_latin_symbols_regexp.test(s);
}

function check_correct_digits(s) {
    return __correct_digits_regexp.test(s);
}

function check_correct_email(s) {
    return __correct_email_regexp.test(s);
}

function mysprintf(num, afterdot) {
    var d = Math.pow(10, afterdot);
    num = Math.round(num * d) / d;

    var a = num.toString().split('.');
    if (!a[1]) a[1] = '';

    while (a[1].length < afterdot) a[1] += '0';

    return a[0] + '.' + a[1];
}

function get_screen_dimensions(what) {
    var viewportwidth;
    var viewportheight;

    if (typeof window.innerWidth != 'undefined') {
        viewportwidth = window.innerWidth;
        viewportheight = window.innerHeight;
    } else if (
        typeof document.documentElement != 'undefined' &&
        typeof document.documentElement.clientWidth != 'undefined' &&
        document.documentElement.clientWidth > 0
    ) {
        viewportwidth = document.documentElement.clientWidth;
        viewportheight = document.documentElement.clientHeight;
    } else {
        viewportwidth = document.getElementsByTagName('body')[0].clientWidth;
        viewportheight = document.getElementsByTagName('body')[0].clientHeight;
    }

    switch (what) {
        case 'x':
        case 'w':
            return viewportwidth;

        case 'y':
        case 'h':
            return viewportheight;

        default:
            return [viewportwidth, viewportheight];
    }
}

function lead0(x) {
    x += '';

    return x.length == 1 ? '0' + x : x;
}

function str_cut_end(s, max_len) {
    var trailer = arguments[2] || '...';

    if (s.length > max_len)
        s = s.substr(0, max_len - trailer.length) + trailer;

    return s;
}


/*
 * Array functions
 */

function arrayKeys(ar) {
    var output = [];
    var counter = 0;

    for (i in ar) {
        if (ar.hasOwnProperty(i)) {
            output[counter++] = i;
        }
    }

    return output;
}

function arraySum(ar, recursive) {
    var key, sum = 0;

    if (ar && !recursive && typeof ar === 'object' && ar.change_key_case) {
        return ar.sum.apply(ar, Array.prototype.slice.call(arguments, 0));
    }

    // input sanitation
    if (typeof ar !== 'object') {
        return null;
    }

    for (key in ar) {
        if (!ar.hasOwnProperty(key)) {
            continue;
        }

        if (typeof ar[key] === 'object') {
            sum += arraySum(ar[key], true);
        } else if (typeof ar[key] === 'boolean') {
            sum += ar[key] ? 1 : 0;
        } else if (!isNaN(parseFloat(ar[key]))) {
            sum += parseFloat(ar[key]);
        }
    }

    return sum;
}

/**
 * Returns count of elements in array/object
 * @param ar array
 * @returns {int|null}
 */
function arrayCount(ar) {
    var cc = 0;

    if (ar && typeof ar === 'object' && ar.change_key_case) {
        return ar.length;
    }

    if (typeof ar !== 'object') {
        return null;
    }

    for (var i in ar) {
        if (ar.hasOwnProperty(i)) {
            cc++;
        }
    }

    return cc;
}

function arrayFlip(trans) {
    var key, tmp = {};

    for (key in trans) {
        if (trans.hasOwnProperty(key)) {
            tmp[trans[key]] = key;
        }
    }

    return tmp;
}

function shuffle(array) {
    for (var i = array.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var temp = array[i];
        array[i] = array[j];
        array[j] = temp;
    }

    return array;
}

function in_array(search_term, ar, strict) {
    var i = ar.length - 1;
    var eq;

    if (i >= 0) {
        do {
            eq = strict
                ? ar[i] === search_term
                : ar[i] == search_term;

            if (eq) {
                return true;
            }
        } while (i--);
    }

    return false;
}

/**
 * @deprecated
 * @param ar
 * @returns {boolean}
 */
function is_array(ar) {
    return di.isArray(ar);
}

function index_of(obj, ar) {
    for (i in ar) if (ar[i] == obj) return i;

    return -1;
}

function ar_indexOf(elt, ar /*, from*/) {
    var len = ar.length;

    var from = Number(arguments[1]) || 0;
    from = from < 0 ? Math.ceil(from) : Math.floor(from);

    if (from < 0) from += len;

    //for (; from < len; from++)
    for (var i in ar) {
        if (ar[i] == elt)
            return i;
    }

    return -1;
}

function divide3dig(s, divider) {
    if (typeof divider == 'undefined') var divider = ',';

    s = s.toString();

    var x = s.indexOf('.');
    var s2 = x != -1 ? s.substr(x) : '';
    s = x != -1 ? s.substr(0, x) : s;

    var ss = '';
    var start = s.length - 3;
    var j = Math.ceil(s.length / 3);
    var len;

    for (var i = 0; i < j; i++) {
        len = 3;

        if (start < 0) {
            len += start;
            start = 0;
        }

        ss = s.substr(start, len) + divider + ss;

        start -= 3;
    }

    ss = ss.substr(0, ss.length - divider.length);

    return ss + s2;
}

function array_splice(arr, offst, lgth, replacement) {
    // Removes the elements designated by offset and length and replace them with supplied array
    //
    // version: 1008.1718
    // discuss at: http://phpjs.org/functions/array_splice
    // +   original by: Brett Zamir (http://brett-zamir.me)
    // +   input by: Theriault
    // %        note 1: Order does get shifted in associative array input with numeric indices,
    // %        note 1: since PHP behavior doesn't preserve keys, but I understand order is
    // %        note 1: not reliable anyways
    // %        note 2: Note also that IE retains information about property position even
    // %        note 2: after being supposedly deleted, so use of this function may produce
    // %        note 2: unexpected results in IE if you later attempt to add back properties
    // %        note 2: with the same keys that had been deleted
    // -    depends on: is_int
    // *     example 1: input = {4: "red", 'abc': "green", 2: "blue", 'dud': "yellow"};
    // *     example 1: array_splice(input, 2);
    // *     returns 1: {0: "blue", 'dud': "yellow"}
    // *     results 1: input == {'abc':"green", 0:"red"}
    // *     example 2: input = ["red", "green", "blue", "yellow"];
    // *     example 2: array_splice(input, 3, 0, "purple");
    // *     returns 2: []
    // *     results 2: input == ["red", "green", "blue", "purple", "yellow"]
    // *     example 3: input = ["red", "green", "blue", "yellow"]
    // *     example 3: array_splice(input, -1, 1, ["black", "maroon"]);
    // *     returns 3: ["yellow"]
    // *     results 3: input == ["red", "green", "blue", "black", "maroon"]

    var _checkToUpIndices = function(arr, ct, key) {
        // Deal with situation, e.g., if encounter index 4 and try to set it to 0, but 0 exists later in loop (need to
        // increment all subsequent (skipping current key, since we need its value below) until find unused)
        if (arr[ct] !== undefined) {
            var tmp = ct;
            ct += 1;
            if (ct === key) {
                ct += 1;
            }
            ct = _checkToUpIndices(arr, ct, key);
            arr[ct] = arr[tmp];
            delete arr[tmp];
        }
        return ct;
    };

    if (replacement && typeof replacement !== 'object') {
        replacement = [replacement];
    }
    if (lgth === undefined) {
        lgth = offst >= 0 ? arr.length - offst : -offst;
    } else if (lgth < 0) {
        lgth = (offst >= 0 ? arr.length - offst : -offst) + lgth;
    }

    if (!(arr instanceof Array)) {
        /*if (arr.length !== undefined) { // Deal with array-like objects as input
         delete arr.length;
         }*/
        var lgt = 0, ct = -1, rmvd = [], rmvdObj = {}, repl_ct = -1, int_ct = -1;
        var returnArr = true, rmvd_ct = 0, rmvd_lgth = 0, key = '';
        // rmvdObj.length = 0;
        for (key in arr) { // Can do arr.__count__ in some browsers
            lgt += 1;
        }
        offst = (offst >= 0) ? offst : lgt + offst;
        for (key in arr) {
            ct += 1;
            if (ct < offst) {
                if (this.is_int(key)) {
                    int_ct += 1;
                    if (parseInt(key, 10) === int_ct) { // Key is already numbered ok, so don't need to change key for value
                        continue;
                    }
                    _checkToUpIndices(arr, int_ct, key); // Deal with situation, e.g.,
                    // if encounter index 4 and try to set it to 0, but 0 exists later in loop
                    arr[int_ct] = arr[key];
                    delete arr[key];
                }
                continue;
            }
            if (returnArr && this.is_int(key)) {
                rmvd.push(arr[key]);
                rmvdObj[rmvd_ct++] = arr[key]; // PHP starts over here too
            } else {
                rmvdObj[key] = arr[key];
                returnArr = false;
            }
            rmvd_lgth += 1;
            // rmvdObj.length += 1;

            if (replacement && replacement[++repl_ct]) {
                arr[key] = replacement[repl_ct];
            } else {
                delete arr[key];
            }
        }
        // arr.length = lgt - rmvd_lgth + (replacement ? replacement.length : 0); // Make (back) into an array-like object
        return returnArr ? rmvd : rmvdObj;
    }

    if (replacement) {
        replacement.unshift(offst, lgth);
        return Array.prototype.splice.apply(arr, replacement);
    }
    return arr.splice(offst, lgth);
}

function isleapyear(year) {
    return year % 4 == 0 && year % 100 != 0 || year % 400 == 0;
}

function get_wd(date_obj) {
    return date_obj.getDay() || 7;
}

function get_yday(date_obj) {
    return date_obj && typeof date_obj == 'object' ? Math.floor((date_obj - (new Date(date_obj.getFullYear(), 0, 1))) / 86400000) : 0;
}

function get_big_yday(y, yday) {
    if (typeof yday == 'undefined') {
        if (!y || typeof y != 'object')
            return 0;

        yday = get_yday(y);
        y = y.getFullYear();
    }

    yday += '';

    while (yday.length < 3)
        yday = '0' + yday;

    return (y + '' + yday) * 1;
}

function get_time(date_obj) {
    return date_obj && typeof date_obj == 'object' ? Math.round(date_obj.getTime() / 1000) : 0;
}

// start could be ? or #
function parse_uri_params(uri/* = location.href*/, start/* = '#'*/, delimiter/* = '&'*/, equal/* = '='*/) {
    uri = uri || window.location.href;
    start = start || '#';

    var ar = {};

    if (uri.indexOf(start) > -1) {
        uri = uri.substr(uri.indexOf(start) + 1);

        if (start == '?' && uri.indexOf('#') > -1) {
            uri = uri.substr(0, uri.indexOf('#'));
        }

        var ar2 = uri.split(delimiter || '&');

        for (var i = 0; i < ar2.length; i++) {
            var ar3 = ar2[i].split(equal || '=');

            ar[ar3[0]] = ar3[1];
        }
    }

    return ar;
}

function serialize_uri(ar) {
    var s = kill_uri_params('', [], ar);

    return s ? s.substr(1) : s;
}

// kill_params is simple array [key1, key2, ...]
// add_params is a hash array {k1: v1, k2: v2, ...}
function kill_uri_params(uri, kill_params, add_params) {
    if (typeof kill_params == 'string') {
        kill_params = [kill_params];
    }

    add_params = add_params || [];

    var x = uri.indexOf('?'),
        base = x == -1 ? uri : uri.substr(0, x),
        ar = parse_uri_params(uri, '?'),
        new_ar = [],
        used_add_params = [];

    for (var i = 0; i < ar.length; i++) {
        if (!in_array(i, kill_params)) {
            new_ar.push(i + '=' + ar[i]);
        } else if (typeof add_params[i] != 'undefined') {
            new_ar.push(i + '=' + add_params[i]);
            used_add_params.push(i);
        }
    }

    for (i = 0; i < add_params.length; i++) {
        if (!in_array(i, used_add_params)) {
            new_ar.push(i + '=' + add_params[i]);
        }
    }

    return base + (new_ar.length ? '?' + new_ar.join('&') : '');
}

function getScrollXY() {
    var scrOfX = 0, scrOfY = 0;
    if (typeof (window.pageYOffset) == 'number') {
        //Netscape compliant
        scrOfY = window.pageYOffset;
        scrOfX = window.pageXOffset;
    } else if (document.body && (document.body.scrollLeft || document.body.scrollTop)) {
        //DOM compliant
        scrOfY = document.body.scrollTop;
        scrOfX = document.body.scrollLeft;
    } else if (document.documentElement && (document.documentElement.scrollLeft || document.documentElement.scrollTop)) {
        //IE6 standards compliant mode
        scrOfY = document.documentElement.scrollTop;
        scrOfX = document.documentElement.scrollLeft;
    }
    return [scrOfX, scrOfY];
}

function check_ctrl_enter(e, form) {
    if (!e) {
        e = window.event;
    }

    if (((e.keyCode == 13) || (e.keyCode == 10)) && (e.ctrlKey == true)) {
        if (typeof form.onsubmit == 'function')
            form.onsubmit();
        else
            form.submit();
    }
}

/**
 * @deprecated
 */
function trim(s) {
    return di.trim(s);
}

/**
 * @deprecated
 */
function ltrim(s) {
    return di.ltrim(s);
}

/**
 * @deprecated
 */
function rtrim(s) {
    return di.rtrim(s);
}

function strip_tags(input, allowed) {
    allowed = (((allowed || '') + '').toLowerCase().match(/<[a-z][a-z0-9]*>/g) || []).join('');
    var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi,
        commentsAndPhpTags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi;

    return input.replace(commentsAndPhpTags, '').replace(tags, function($0, $1) {
        return allowed.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : '';
    });
}

/**
 * @deprecated
 */
function urlencode(s) {
    return di.urlencode(s);
}

/**
 * @deprecated
 */
function urldecode(s) {
    return di.urldecode(s);
}

function size_in_bytes(size, mb, kb, b) {
    mb = mb || 'Mb';
    kb = kb || 'kb';
    b = b || 'bytes';

    if (size > 1048576) return (Math.round(size * 10 / 1048576) / 10) + mb;
    else if (size > 1024) return (Math.round(size * 10 / 1024) / 10) + kb;
    else return size + b;
}

function from_size_in_bytes(s) {
    var ar = {
        'bytes': 1,
        'байт': 1,
        'kb': 1024,
        'кб': 1024,
        'mb': 1048576,
        'мб': 1048576,
        'gb': 1073741824,
        'гб': 1073741824,
    };

    s = trim(s).toLowerCase();

    for (var i in ar) {
        if (s.substr(-i.length) == i) {
            s = trim(s.substr(0, s.length - i.length));
            s = s.replace(',', '.');
            s = parseFloat(s);
            s *= ar[i];

            break;
        }
    }

    return s;
}

function get_cursor_position(e) {
    if (e.selectionStart)
        return e.selectionStart;
    else if (document.selection) {
        e.focus();

        var r = document.selection.createRange();
        if (r == null)
            return 0;

        var re = e.createTextRange(), rc = re.duplicate();
        re.moveToBookmark(r.getBookmark());
        rc.setEndPoint('EndToStart', re);

        return rc.text.length;
    }

    return 0;
}

function set_cursor_position(e, position) {
    if (e.createTextRange) {
        var range = e.createTextRange();
        range.move('character', position);
        range.select();
    } else {
        if (e.selectionStart) {
            e.focus();
            e.setSelectionRange(position, position);
        } else {
            e.focus();
        }
    }
}

function digit_case(x, s1, s2, s3/* = false*/, only_string/* = false*/) {
    if (typeof s3 == 'undefined')
        var s3 = false;

    if (typeof only_string == 'undefined')
        var only_string = false;

    if (s3 === false) s3 = s2;

    if (x % 10 == 1 && x != 11)
        return only_string ? s1 : x + ' ' + s1;
    else if (x % 10 >= 2 && x % 10 <= 4 && x != 12 && x != 13 && x != 14)
        return only_string ? s2 : x + ' ' + s2;
    else
        return only_string ? s3 : x + ' ' + s3;
}

function basename(str, suffix) {
    var x = str.lastIndexOf('/');
    if (x == -1) x = str.lastIndexOf('\\');

    var base = new String(str).substring(x + 1);

    if (typeof (suffix) == 'string' && base.substr(base.length - suffix.length) == suffix) {
        base = base.substr(0, base.length - suffix.length);
    }

    return base;
}

function dirname(str) {
    var x = str.lastIndexOf('/');
    if (x == -1) x = str.lastIndexOf('\\');

    return x != -1 ? new String(str).substring(0, x) : str;
}

function get_file_ext(fn) {
    return fn.split('.').pop();
}

function highlight(container, what, spanClass) {
    var content = container.innerHTML,
        pattern = new RegExp('(>[^<.]*)(' + what + ')([^<.]*)', 'g'),
        replaceWith = '$1<span ' + (spanClass ? 'class="' + spanClass + '"' : '') + '">$2</span>$3',
        highlighted = content.replace(pattern, replaceWith);

    return (container.innerHTML = highlighted) !== content;
}

function get_age(d, m, y) {
    var today = new Date();
    var md = lead0(today.getMonth() + 1) + lead0(today.getDate());

    return y ? today.getFullYear() - y - (md < lead0(m) + lead0(d) ? 1 : 0) : 0;
}

function print_r(arr, level) {
    var print_red_text = '';
    if (!level) level = 0;
    var level_padding = '';
    for (var j = 0; j < level + 1; j++) level_padding += '    ';
    if (typeof (arr) == 'object') {
        for (var item in arr) {
            var value = arr[item];
            if (typeof (value) == 'object') {
                print_red_text += level_padding + '\'' + item + '\' :\n';
                print_red_text += print_r(value, level + 1);
            } else
                print_red_text += level_padding + '\'' + item + '\' => "' + value + '"\n';
        }

        if (!print_red_text)
            print_red_text = '{}';
        else
            print_red_text = level_padding + '{\n' + print_red_text + '\n' + level_padding + '}';
    } else
        print_red_text = level_padding + '(' + typeof (arr) + ')' + arr;

    return print_red_text;
}

function goto(uri) {
    window.location.href = uri;
}

function goto_new(uri) {
    window.open(uri, '', ''); //width=700,height=400,left=200,top=200
}

function get_modes() {
    return window.location.pathname.replace(/^\/+|\/+$/g, '').split('/');
}

function do_window_have_scroll_bar() {
    return $('body').height() > $(window).height();
}

function sprintf() {
    var args = arguments,
        string = args[0],
        i = 1;

    return string.replace(/%((%)|s|d)/g, function(m) {
        // m is the matched format, e.g. %s, %d
        var val = null;
        if (m[2]) {
            val = m[2];
        } else {
            val = args[i];
            // A switch statement so that the formatter can be extended. Default is %s
            switch (m) {
                case '%d':
                    val = parseFloat(val);
                    if (isNaN(val)) {
                        val = 0;
                    }
                    break;
            }
            i++;
        }

        return val;
    });
}

function hexToRgb(hex) {
    // Expand shorthand form (e.g. "03F") to full form (e.g. "0033FF")
    var shorthandRegex = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
    hex = hex.replace(shorthandRegex, function(m, r, g, b) {
        return r + r + g + g + b + b;
    });

    var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16),
    } : null;
}


function run_movie(swf, w, h, wmode, flash_vars, id) {
    return print_flash_code(swf, w, h, wmode, flash_vars, id);
}

function print_flash_code(swf, w, h, wmode, flash_vars, id) {
    if (!flash_vars) flash_vars = '';
    document.write(get_flash_code(swf, w, h, wmode, flash_vars, id));
    return true;
}

function get_flash_code(swf, w, h, wmode, flash_vars, id) {
    var embed_text = '';

    if (!id || typeof id == 'undefined')
        var id = swf;

    if (typeof wmode != 'undefined' && wmode) {
        embed_text += ' wmode="' + wmode + '"';
    }

    if (typeof flash_vars != 'undefined' && flash_vars) {
        embed_text += ' FlashVars="' + flash_vars + '"';
    }
    //exactfit
    return '<embed id="' + id + '" name="' + id + '" src="' + swf + '" quality="high" scale="exactfit" ' +
        'type="application/x-shockwave-flash" width="' + w + '" height="' + h + '" menu="false" ' + embed_text +
        ' bgcolor="#ffffff" allowScriptAccess="sameDomain" allowFullScreen="true" />\r\n';
}

function insert_flash_code(container_id, swf, w, h, wmode, flash_vars, id) {
    var e = _ge(container_id);
    if (e) {
        if (!flash_vars) flash_vars = '';
        e.innerHTML = get_flash_code(swf, w, h, wmode, flash_vars, id);
    }
}

function getFlashObject(movieName, win) {
    win = win || window;

    if (navigator.appName.indexOf('Microsoft') != -1) {
        return win[movieName];
    } else {
        return win.document[movieName];
    }
}

Math.sqr = Math.sqr || function(x) {
    return x * x;
};
Math.hypot = Math.hypot || function(x, y) {
    return Math.sqrt(x * x + y * y);
};

function getCssPropertyAr(property, value, stringResult) {
    var prefixes = ['-ms-', '-moz-', '-o-', '-webkit-', '-khtml-', ''],
        ar = {},
        i,
        s = '';

    for (i = 0; i < prefixes.length; i++) {
        if (stringResult) {
            s += prefixes[i] + property + ':' + value + ';';
        } else {
            ar[prefixes[i] + property] = value;
        }
    }

    return stringResult ? s : ar;
}

function getCaret(el) {
    if (el.selectionStart) {
        return el.selectionStart;
    } else if (document.selection) {
        el.focus();

        var r = document.selection.createRange();

        if (r == null) {
            return 0;
        }

        var re = el.createTextRange(),
            rc = re.duplicate();

        re.moveToBookmark(r.getBookmark());
        rc.setEndPoint('EndToStart', re);

        return rc.text.length;
    }

    return 0;
}

function get_unique_id(len) {
    len = len || 32;
    var text = '';
    var possible = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    for (var i = 0; i < len; i++) {
        text += possible.charAt(Math.floor(Math.random() * possible.length));
    }

    return text;
}

function getRandomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

window.requestAnimFrame = function() {
    return (
        window.requestAnimationFrame ||
        window.webkitRequestAnimationFrame ||
        window.mozRequestAnimationFrame ||
        window.oRequestAnimationFrame ||
        window.msRequestAnimationFrame ||
        function(/* function */ callback) {
            window.setTimeout(callback, 1000 / 60);
        }
    );
}();

function hasGetUserMedia() {
    return !!(navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia);
}

function isElementInViewport(el) {
    if (typeof jQuery === 'function' && el instanceof jQuery) {
        el = el[0];
    }

    var rect = el.getBoundingClientRect();

    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && /*or $(window).height() */
        rect.right <= (window.innerWidth || document.documentElement.clientWidth) /*or $(window).width() */
    );
}

if (!String.prototype.format) {
    /**
     * Usage: 'Hello, {0}! {1} to see you'.format('James', 'Nice');
     * @returns {string}
     */
    String.prototype.format = function() {
        var args = arguments;

        return this.replace(/{(\d+)}/g, function(match, number) {
            return typeof args[number] != 'undefined'
                ? args[number]
                : match;
        });
    };
}

String.prototype.toCamelCase = function() {
    return this.replace(/(\-[a-z])/g, function($1) {
        return $1.toUpperCase().replace('-', '');
    });
};

String.prototype.toUnderscore = function() {
    return this.replace(/([A-Z])/g, function($1) {
        return '_' + $1.toLowerCase();
    });
};

function insertCss(code, opts) {
    opts = opts || {};

    var style = document.createElement('style');
    style.type = 'text/css';

    if (opts.id) {
        style.id = opts.id;
    }

    if (style.styleSheet) {
        style.styleSheet.cssText = code;
    } else {
        style.innerHTML = code;
    }

    return document.getElementsByTagName('head')[0].appendChild(style);
}

function array_unique(array) {
    return jQuery.grep(array, function(el, index) {
        return index === jQuery.inArray(el, array);
    });
}