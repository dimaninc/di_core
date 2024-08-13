/**
 * Created by dimaninc on 04.05.2016.
 */

var di = {
    // di_core api

    workerPrefix: '/api/',
    workerAdminPrefix: '/api/',

    getWorkerBasePath: function (controller, action, params, options) {
        options = $.extend(
            {
                underscoreSuffix: true
            },
            options || {}
        );
        suffixAr = [];

        if (controller) {
            if (di.isArray(controller) && !action && !params) {
                suffixAr = controller;
            } else {
                suffixAr.push(controller);

                if (action) {
                    suffixAr.push(action);
                }

                if (params) {
                    if (!di.isArray(params)) {
                        params = [params];
                    }

                    suffixAr = suffixAr.concat(params);
                }
            }
        }

        if (suffixAr) {
            if (options.underscoreSuffix) {
                if (suffixAr.length >= 1) {
                    suffixAr[0] = di.underscore(suffixAr[0]);
                }

                if (suffixAr.length >= 2) {
                    suffixAr[1] = di.underscore(suffixAr[1]);
                }
            }

            suffixAr.push('');
        }

        return suffixAr.join('/');
    },

    getWorkerPath: function (controller, action, params, options) {
        return (
            di.workerPrefix +
            di.getWorkerBasePath(controller, action, params, options)
        );
    },

    getAdminWorkerPath: function (controller, action, params, options) {
        return (
            di.workerAdminPrefix +
            di.getWorkerBasePath(controller, action, params, options)
        );
    },

    // common

    asap: function (whenToStart, whatToDo, delay, maxTries) {
        var opts = $.extend(
                {
                    whenToStart:
                        whenToStart ||
                        function () {
                            return true;
                        },
                    whatToDo:
                        whatToDo ||
                        function () {
                            return true;
                        },
                    maxTries: maxTries,
                    delay: delay || 50
                },
                typeof whenToStart === 'object' ? whenToStart : {}
            ),
            interval,
            tries = 0;

        function start() {
            interval = setInterval(function () {
                if (opts.whenToStart()) {
                    opts.whatToDo();
                    stop();
                } else if (opts.maxTries && ++tries === opts.maxTries) {
                    stop();
                }
            }, opts.delay);
        }

        function stop() {
            clearInterval(interval);
        }

        start();
    },

    supported: {
        advancedUploading: (function () {
            var div = document.createElement('div');
            return (
                ('draggable' in div || ('ondragstart' in div && 'ondrop' in div)) &&
                'FormData' in window &&
                'FileReader' in window
            );
        })()
    },

    // loaders

    loadScript: function (url, callback) {
        var script = document.createElement('script');
        script.src = url;
        script.onload = callback;

        var x = document.getElementsByTagName('script')[0];
        x.parentNode.insertBefore(script, x);
    },

    loadStyle: function (url, callback) {
        var link = document.createElement('link');
        link.href = url;
        link.type = 'text/css';
        link.rel = 'stylesheet';
        link.media = 'screen,print';
        link.onload = callback;

        document.getElementsByTagName('head')[0].appendChild(link);
    },

    // string

    wordWrap: function (str, width, separator, cut) {
        separator = separator || null;
        width = width || 75;
        cut = cut || false;

        if (!str) {
            return separator === null ? [str] : str;
        }

        var regex =
                '.{1,' +
                width +
                '}(\\s|$)' +
                (cut ? '|.{' + width + '}|.+$' : '|\\S+?(\\s|$)'),
            lines = str.match(RegExp(regex, 'g'));

        return separator === null ? lines : lines.join(separator);
    },

    trim: function (s) {
        return s.replace(/^\s+|\s+$/g, '');
    },

    ltrim: function (s) {
        return s.replace(/^\s+/, '');
    },

    rtrim: function (s) {
        return s.replace(/\s+$/, '');
    },

    startsWith: function (haystack, needle) {
        if (!haystack || !needle) {
            return false;
        }

        return haystack.substr(0, needle.length) === needle;
    },

    endsWith: function (haystack, needle) {
        if (!haystack || !needle) {
            return false;
        }

        return haystack.substr(-needle.length) === needle;
    },

    contains: function (haystack, needle) {
        if (!haystack || !needle) {
            return false;
        }

        return haystack.indexOf(needle) > -1;
    },

    nl2br: function (s) {
        return s.replace(/[\r\n]+/, '<br>\n');
    },

    underscore: function (s) {
        return (s + '').toUnderscore();
    },

    camelize: function (s) {
        return (s + '').toCamelCase();
    },

    urlencode: function (s) {
        return encodeURIComponent(s);
    },

    urldecode: function (s) {
        return decodeURIComponent((s + '').replace(/\+/g, '%20'));
    },

    escapeHtml: function (text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return text.replace(/[&<>"']/g, function (m) {
            return map[m];
        });
    },

    getUrlGlue: function (href) {
        return (href || '').indexOf('?') > -1 ? '&' : '?';
    },

    strReplace: function (search, replace, subject, count) {
        //  discuss at: http://phpjs.org/functions/str_ireplace/
        // original by: Glen Arason (http://CanadianDomainRegistry.ca)
        //        note: Case-insensitive version of str_replace()
        //        note: Compliant with PHP 5.0 str_ireplace() Full details at:
        //        note: http://ca3.php.net/manual/en/function.str-ireplace.php
        //        note: The count parameter (optional) if used must be passed in as a
        //        note: string. eg global var MyCount:
        //        note: str_ireplace($search, $replace, $subject, 'MyCount');
        //      format: str_ireplace($search, $replace, $subject[, 'count'])
        //       input: str_ireplace($search, $replace, $subject[, {string}]);

        var i = 0,
            j = 0,
            temp = '',
            repl = '',
            sl = 0,
            fl = 0,
            f = '',
            r = '',
            s = '',
            ra = '',
            sa = '',
            otemp = '',
            oi = '',
            ofjl = '',
            os = subject,
            osa = Object.prototype.toString.call(os) === '[object Array]';

        if (typeof search === 'object') {
            temp = search;
            search = new Array();
            for (i = 0; i < temp.length; i += 1) {
                search[i] = temp[i].toLowerCase();
            }
        } else {
            search = search.toLowerCase();
        }

        if (typeof subject === 'object') {
            temp = subject;
            subject = new Array();
            for (i = 0; i < temp.length; i += 1) {
                subject[i] = temp[i].toLowerCase();
            }
        } else {
            subject = subject.toLowerCase();
        }

        if (typeof search === 'object' && typeof replace === 'string') {
            temp = replace;
            replace = new Array();
            for (i = 0; i < search.length; i += 1) {
                replace[i] = temp;
            }
        }

        temp = '';
        f = [].concat(search);
        r = [].concat(replace);
        ra = Object.prototype.toString.call(r) === '[object Array]';
        s = subject;
        sa = Object.prototype.toString.call(s) === '[object Array]';
        s = [].concat(s);
        os = [].concat(os);

        if (count) {
            this.window[count] = 0;
        }

        for (i = 0, sl = s.length; i < sl; i++) {
            if (s[i] === '') {
                continue;
            }
            for (j = 0, fl = f.length; j < fl; j++) {
                temp = s[i] + '';
                repl = ra ? (r[j] !== undefined ? r[j] : '') : r[0];
                s[i] = temp.split(f[j]).join(repl);
                otemp = os[i] + '';
                oi = temp.indexOf(f[j]);
                ofjl = f[j].length;
                if (oi >= 0) {
                    os[i] = otemp.split(otemp.substr(oi, ofjl)).join(repl);
                }

                if (count) {
                    this.window[count] += temp.split(f[j]).length - 1;
                }
            }
        }

        return osa ? os : os[0];
    },

    // number

    round: function (number, precision) {
        if (!precision) {
            return Math.round(number);
        }

        var factor = Math.pow(10, precision);
        var tempNumber = number * factor;
        var roundedTempNumber = Math.round(tempNumber);

        return roundedTempNumber / factor;
    },

    // array

    isArray: function (ar) {
        return Object.prototype.toString.call(ar) === '[object Array]';
    },

    arrayFilter: function (ar, callback) {
        callback =
            callback ||
            function (e) {
                return !!e;
            };

        return ar.filter(callback);
    },

    // object

    objectFilter: function (obj, callback) {
        var res = {};
        var key;

        callback =
            callback ||
            function (e) {
                return !!e;
            };

        for (key in obj) {
            if (obj.hasOwnProperty(key) && callback(obj[key])) {
                res[key] = obj[key];
            }
        }

        return res;
    },

    keys: function (obj) {
        return $.map(obj, function (val, key) {
            return key;
        });
    },

    values: function (obj) {
        return $.map(obj, function (val, key) {
            return val;
        });
    }
};
