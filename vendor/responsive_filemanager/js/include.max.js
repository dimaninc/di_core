var encodeURL, show_animation, hide_animation, apply, apply_none, apply_img, apply_any, apply_video, apply_link, apply_file_rename, apply_file_duplicate, apply_folder_rename;
! function(e, a, r) {
    "use strict";

    function t(e) {
        show_animation();
        var a = new Image;
        a.src = e, jQuery(a).on("load", function() {
            hide_animation()
        })
    }

    function n() {
        jQuery("#textfile_create_area").parent().parent().remove(), e.ajax({
            type: "GET",
            url: "ajax_calls.php?action=new_file_form"
        }).done(function(a) {
            bootbox.dialog(a, [{
                label: jQuery("#cancel").val(),
                "class": "btn"
            }, {
                label: jQuery("#ok").val(),
                "class": "btn-inverse",
                callback: function() {
                    var a = jQuery("#create_text_file_name").val() + jQuery("#create_text_file_extension").val(),
                        r = jQuery("#textfile_create_area").val();
                    if (null !== a) {
                        a = _(a);
                        var t = jQuery("#sub_folder").val() + jQuery("#fldr_value").val();
                        e.ajax({
                            type: "POST",
                            url: "execute.php?action=create_file",
                            data: {
                                path: t,
                                name: a,
                                new_content: r
                            }
                        }).done(function(e) {
                            "" != e && bootbox.alert(e, function() {
                                setTimeout(function() {
                                    window.location.href = jQuery("#refresh").attr("href") + "&" + (new Date).getTime()
                                }, 500)
                            })
                        })
                    }
                }
            }], {
                header: jQuery("#lang_new_file").val()
            })
        })
    }

    function i(a) {
        jQuery("#textfile_edit_area").parent().parent().remove();
        var r = a.find(".rename-file-paths").attr("data-path");
        e.ajax({
            type: "POST",
            url: "ajax_calls.php?action=get_file&sub_action=edit&preview_mode=text",
            data: {
                path: r
            }
        }).done(function(t) {
            bootbox.dialog(t, [{
                label: jQuery("#cancel").val(),
                "class": "btn"
            }, {
                label: jQuery("#ok").val(),
                "class": "btn-inverse",
                callback: function() {
                    var a = jQuery("#textfile_edit_area").val();
                    e.ajax({
                        type: "POST",
                        url: "execute.php?action=save_text_file",
                        data: {
                            path: r,
                            new_content: a
                        }
                    }).done(function(e) {
                        "" != e && bootbox.alert(e)
                    })
                }
            }], {
                header: a.find(".name_download").val()
            })
        })
    }

    function l() {
        e.ajax({
            type: "POST",
            url: "ajax_calls.php?action=get_lang",
            data: {}
        }).done(function(a) {
            bootbox.dialog(a, [{
                label: jQuery("#cancel").val(),
                "class": "btn"
            }, {
                label: jQuery("#ok").val(),
                "class": "btn-inverse",
                callback: function() {
                    var a = jQuery("#new_lang_select").val();
                    e.ajax({
                        type: "POST",
                        url: "ajax_calls.php?action=change_lang",
                        data: {
                            choosen_lang: a
                        }
                    }).done(function(e) {
                        "" != e ? bootbox.alert(e) : setTimeout(function() {
                            window.location.href = jQuery("#refresh").attr("href").replace(/lang=[\w]*&/i, "lang=" + a + "&") + "&" + (new Date).getTime()
                        }, 100)
                    })
                }
            }], {
                header: jQuery("#lang_lang_change").val()
            })
        })
    }

    function o(a) {
        jQuery("#files_permission_start").parent().parent().remove();
        var r = a.find(".rename-file-paths"),
            t = r.attr("data-path"),
            n = r.attr("data-permissions"),
            i = r.attr("data-folder");
        e.ajax({
            type: "POST",
            url: "ajax_calls.php?action=chmod",
            data: {
                path: t,
                permissions: n,
                folder: i
            }
        }).done(function(a) {
            bootbox.dialog(a, [{
                label: jQuery("#cancel").val(),
                "class": "btn"
            }, {
                label: jQuery("#ok").val(),
                "class": "btn-inverse",
                callback: function() {
                    var a = "-";
                    a += jQuery("#u_4").is(":checked") ? "r" : "-", a += jQuery("#u_2").is(":checked") ? "w" : "-", a += jQuery("#u_1").is(":checked") ? "x" : "-", a += jQuery("#g_4").is(":checked") ? "r" : "-", a += jQuery("#g_2").is(":checked") ? "w" : "-", a += jQuery("#g_1").is(":checked") ? "x" : "-", a += jQuery("#a_4").is(":checked") ? "r" : "-", a += jQuery("#a_2").is(":checked") ? "w" : "-", a += jQuery("#a_1").is(":checked") ? "x" : "-";
                    var n = jQuery("#chmod_form #chmod_value").val();
                    if ("" != n && "undefined" != typeof n) {
                        var l = jQuery("#chmod_form input[name=apply_recursive]:checked").val();
                        "" != l && "undefined" != typeof l || (l = "none"), e.ajax({
                            type: "POST",
                            url: "execute.php?action=chmod",
                            data: {
                                path: t,
                                new_mode: n,
                                is_recursive: l,
                                folder: i
                            }
                        }).done(function(e) {
                            "" != e ? bootbox.alert(e) : r.attr("data-permissions", a)
                        })
                    }
                }
            }], {
                header: jQuery("#lang_file_permission").val()
            }), setTimeout(function() {
                u(!1)
            }, 100)
        })
    }

    function u(a) {
        var r = [];
        if (r.user = 0, r.group = 0, r.all = 0, "undefined" != typeof a && 1 == a) {
            var t = jQuery("#chmod_form #chmod_value").val();
            r.user = t.substr(0, 1), r.group = t.substr(1, 1), r.all = t.substr(2, 1), e.each(r, function(a) {
                ("" == r[a] || 0 == e.isNumeric(r[a]) || parseInt(r[a]) < 0 || parseInt(r[a]) > 7) && (r[a] = "0")
            }), jQuery("#chmod_form input:checkbox").each(function() {
                var e = jQuery(this).attr("data-group"),
                    a = jQuery(this).attr("data-value");
                c(r[e], a) ? jQuery(this).prop("checked", !0) : jQuery(this).prop("checked", !1)
            })
        } else jQuery("#chmod_form input:checkbox:checked").each(function() {
            var e = jQuery(this).attr("data-group"),
                a = jQuery(this).attr("data-value");
            r[e] = parseInt(r[e]) + parseInt(a)
        }), jQuery("#chmod_form #chmod_value").val(r.user.toString() + r.group.toString() + r.all.toString())
    }

    function c(a, r) {
        var t = [];
        return t[1] = [1, 3, 5, 7], t[2] = [2, 3, 6, 7], t[4] = [4, 5, 6, 7], a = parseInt(a), r = parseInt(r), e.inArray(a, t[r]) != -1
    }

    function s() {
        bootbox.confirm(jQuery("#lang_clear_clipboard_confirm").val(), jQuery("#cancel").val(), jQuery("#ok").val(), function(a) {
            1 == a && e.ajax({
                type: "POST",
                url: "ajax_calls.php?action=clear_clipboard",
                data: {}
            }).done(function(e) {
                "" != e ? bootbox.alert(e) : jQuery("#clipboard").val("0"), y(!1)
            })
        })
    }

    function d(a, r) {
        if ("copy" == r || "cut" == r) {
            var t;
            t = a.hasClass("directory") ? a.find(".rename-file-paths").attr("data-path") : a.find(".rename-file-paths").attr("data-path"), e.ajax({
                type: "POST",
                url: "ajax_calls.php?action=copy_cut",
                data: {
                    path: t,
                    sub_action: r
                }
            }).done(function(e) {
                "" != e ? bootbox.alert(e) : (jQuery("#clipboard").val("1"), y(!0))
            })
        }
    }

    function f(a) {
        bootbox.confirm(jQuery("#lang_paste_confirm").val(), jQuery("#cancel").val(), jQuery("#ok").val(), function(r) {
            if (1 == r) {
                var t;
                t = "undefined" != typeof a ? a.find(".rename-folder").attr("data-path") : jQuery("#sub_folder").val() + jQuery("#fldr_value").val(), e.ajax({
                    type: "POST",
                    url: "execute.php?action=paste_clipboard",
                    data: {
                        path: t
                    }
                }).done(function(e) {
                    "" != e ? bootbox.alert(e) : (jQuery("#clipboard").val("0"), y(!1), setTimeout(function() {
                        window.location.href = jQuery("#refresh").attr("href") + "&" + (new Date).getTime()
                    }, 300))
                })
            }
        })
    }

    function p(a, r) {
        var t;
        t = a.hasClass("directory") ? a.find(".rename-folder") : a.find(".rename-file");
        var n = t.attr("data-path");
        a.parent().hide(100), e.ajax({
            type: "POST",
            url: "ajax_calls.php?action=copy_cut",
            data: {
                path: n,
                sub_action: "cut"
            }
        }).done(function(t) {
            if ("" != t) bootbox.alert(t);
            else {
                var n;
                n = "undefined" != typeof r ? r.hasClass("back-directory") ? r.find(".path").val() : r.find(".rename-folder").attr("data-path") : jQuery("#sub_folder").val() + jQuery("#fldr_value").val(), e.ajax({
                    type: "POST",
                    url: "execute.php?action=paste_clipboard",
                    data: {
                        path: n
                    }
                }).done(function(e) {
                    "" != e ? (bootbox.alert(e), a.parent().show(100)) : (jQuery("#clipboard").val("0"), y(!1), a.parent().remove())
                })
            }
        }).error(function() {
            a.parent().show(100)
        })
    }

    function y(e) {
        1 == e ? jQuery(".paste-here-btn, .clear-clipboard-btn").removeClass("disabled") : jQuery(".paste-here-btn, .clear-clipboard-btn").addClass("disabled")
    }

    function v(e) {
        var r = jQuery(".breadcrumb").width() + e,
            t = jQuery("#view"),
            n = jQuery("#help");
        if (jQuery(".uploader").css("width", r), t.val() > 0) {
            if (1 == t.val()) jQuery("ul.grid li, ul.grid figure").css("width", "100%");
            else {
                var i = Math.floor(r / 380);
                0 == i && (i = 1, jQuery("h4").css("font-size", 12)), r = Math.floor(r / i - 3), jQuery("ul.grid li, ul.grid figure").css("width", r)
            }
            n.hide()
        } else a.touch && n.show()
    }

    function m() {
        var e = jQuery(this);
        0 == jQuery("#view").val() && (1 == e.attr("toggle") ? (e.attr("toggle", 0), e.animate({
            top: "0px"
        }, {
            queue: !1,
            duration: 300
        })) : (e.attr("toggle", 1), e.animate({
            top: "-30px"
        }, {
            queue: !1,
            duration: 300
        })))
    }

    function j(e) {
        var a = new RegExp("(?:[?&]|&)" + e + "=([^&]+)", "i"),
            r = window.location.search.match(a);
        return r && r.length > 1 ? r[1] : null
    }

    function Q() {
        1 == jQuery("#popup").val() ? window.close() : ("function" == typeof parent.jQuery(".modal").modal && parent.jQuery(".modal").modal("hide"), "undefined" != typeof parent.jQuery && parent.jQuery ? "function" == typeof parent.jQuery.fancybox && parent.jQuery.fancybox.close() : "function" == typeof parent.$.fancybox && parent.$.fancybox.close())
    }

    function Q() {
        1 == jQuery("#popup").val() ? window.close() : ("function" == typeof parent.jQuery(".modal:has(iframe[src*=filemanager])").modal && parent.jQuery(".modal:has(iframe[src*=filemanager])").modal("hide"), "undefined" != typeof parent.jQuery && parent.jQuery ? "function" == typeof parent.jQuery.fancybox && parent.jQuery.fancybox.close() : "function" == typeof parent.$.fancybox && parent.$.fancybox.close())
    }

    function h(e) {
        for (var e, a = [/[\300-\306]/g, /[\340-\346]/g, /[\310-\313]/g, /[\350-\353]/g, /[\314-\317]/g, /[\354-\357]/g, /[\322-\330]/g, /[\362-\370]/g, /[\331-\334]/g, /[\371-\374]/g, /[\321]/g, /[\361]/g, /[\307]/g, /[\347]/g], r = ["A", "a", "E", "e", "I", "i", "O", "o", "U", "u", "N", "n", "C", "c"], t = 0; t < a.length; t++) e = e.replace(a[t], r[t]);
        return e
    }

    function _(a) {
        return null != a ? ("true" == jQuery("#transliteration").val() && (a = h(a), a = a.replace(/[^A-Za-z0-9\.\-\[\] _]+/g, "")), "true" == jQuery("#convert_spaces").val() && (a = a.replace(/ /g, jQuery("#replace_with").val())), "true" == jQuery("#lower_case").val() && (a = a.toLowerCase()), a = a.replace('"', ""), a = a.replace("'", ""), a = a.replace("/", ""), a = a.replace("\\", ""), a = a.replace(/<\/?[^>]+(>|$)/g, ""), e.trim(a)) : null
    }

    function g(a, r, t, n, i) {
        null !== t && (t = _(t), e.ajax({
            type: "POST",
            url: "execute.php?action=" + a,
            data: {
                path: r,
                name: t.replace("/", "")
            }
        }).done(function(e) {
            return "" != e ? (bootbox.alert(e), !1) : ("" != i && window[i](n, t), !0)
        }))
    }

    function b(a, r) {
        var t = jQuery("li.dir", "ul.grid").filter(":visible"),
            n = jQuery("li.file", "ul.grid").filter(":visible"),
            i = [],
            l = [],
            o = [],
            u = [];
        t.each(function() {
            var a = jQuery(this),
                t = a.find(r).val();
            if (e.isNumeric(t))
                for (t = parseFloat(t);
                     "undefined" != typeof i[t] && i[t];) t = parseFloat(parseFloat(t) + parseFloat(.001));
            else t = t + "a" + a.find("h4 a").attr("data-file");
            i[t] = a.html(), l.push(t)
        }), n.each(function() {
            var a = jQuery(this),
                t = a.find(r).val();
            if (e.isNumeric(t))
                for (t = parseFloat(t);
                     "undefined" != typeof o[t] && o[t];) t = parseFloat(parseFloat(t) + parseFloat(.001));
            else t = t + "a" + a.find("h4 a").attr("data-file");
            o[t] = a.html(), u.push(t)
        }), e.isNumeric(l[0]) ? l.sort(function(e, a) {
            return parseFloat(e) - parseFloat(a)
        }) : l.sort(), e.isNumeric(u[0]) ? u.sort(function(e, a) {
            return parseFloat(e) - parseFloat(a)
        }) : u.sort(), a && (l.reverse(), u.reverse()), t.each(function(e) {
            var a = jQuery(this);
            a.html(i[l[e]])
        }), n.each(function(e) {
            var a = jQuery(this);
            a.html(o[u[e]])
        })
    }

    function w(e, a) {
        return featherEditor.launch({
            image: e,
            url: a
        }), !1
    }

    function x() {
        jQuery(".lazy-loaded").lazyload()
    }
    var k = "9.11.3",
        C = !0,
        T = 0,
        I = function() {
            var e = 0;
            return function(a, r) {
                clearTimeout(e), e = setTimeout(a, r)
            }
        }(),
        S = function(e) {
            if (1 == jQuery("#ftp").val()) var a = jQuery("#ftp_base_url").val() + jQuery("#upload_dir").val() + jQuery("#fldr_value").val();
            else var a = jQuery("#base_url").val() + jQuery("#cur_dir").val();
            var r = e.find("a.link").attr("data-file");
            return "" != r && null != r && (a += r), r = e.find("h4 a.folder-link").attr("data-file"), "" != r && null != r && (a += r), a
        },
        U = {
            contextActions: {
                copy_url: function(e) {
                    var a = S(e);
                    bootbox.alert('URL:<br/><div class="input-append" style="width:100%"><input id="url_text' + T + '" type="text" style="width:80%; height:30px;" value="' + encodeURL(a) + '" /><button id="copy-button' + T + '" class="btn btn-inverse copy-button" style="width:20%; height:30px;" data-clipboard-target="url_text' + T + '" data-clipboard-text="Copy Me!" title="copy"></button></div>'), jQuery("#copy-button" + T).html('<i class="icon icon-white icon-share"></i> ' + jQuery("#lang_copy").val());
                    var r = new ZeroClipboard(jQuery("#copy-button" + T));
                    r.on("ready", function(e) {
                        r.on("wrongFlash noFlash", function() {
                            ZeroClipboard.destroy()
                        }), r.on("aftercopy", function(e) {
                            jQuery("#copy-button" + T).html('<i class="icon icon-ok"></i> ' + jQuery("#ok").val()), jQuery("#copy-button" + T).attr("class", "btn disabled"), T++
                        }), r.on("error", function(e) {})
                    })
                },
                unzip: function(a) {
                    var r = jQuery("#sub_folder").val() + jQuery("#fldr_value").val() + a.find("a.link").attr("data-file");
                    show_animation(), e.ajax({
                        type: "POST",
                        url: "ajax_calls.php?action=extract",
                        data: {
                            path: r
                        }
                    }).done(function(e) {
                        hide_animation(), "" != e ? bootbox.alert(e) : window.location.href = jQuery("#refresh").attr("href") + "&" + (new Date).getTime()
                    })
                },
                edit_img: function(e) {
                    var a = e.attr("data-name");
                    if (1 == jQuery("#ftp").val()) var r = jQuery("#ftp_base_url").val() + jQuery("#upload_dir").val() + jQuery("#fldr_value").val() + a;
                    else var r = jQuery("#base_url").val() + jQuery("#cur_dir").val() + a;
                    var t = jQuery("#aviary_img");
                    t.attr("data-name", a), show_animation(), t.attr("src", r).load(w(t.attr("id"), r))
                },
                duplicate: function(e) {
                    var a = e.find("h4").text().trim();
                    bootbox.prompt(jQuery("#lang_duplicate").val(), jQuery("#cancel").val(), jQuery("#ok").val(), function(r) {
                        if (null !== r && (r = _(r), r != a)) {
                            var t = e.find(".rename-file");
                            g("duplicate_file", t.attr("data-path"), r, t, "apply_file_duplicate")
                        }
                    }, a)
                },
                select: function(e) {
                    var a, r = S(e),
                        t = jQuery("#field_id").val(),
                        n = jQuery("#return_relative_url").val();
                    if (1 == n && (r = r.replace(jQuery("#base_url").val(), ""), r = r.replace(jQuery("#cur_dir").val(), "")), a = 1 == jQuery("#popup").val() ? window.opener : window.parent, "" != t)
                        if (1 == jQuery("#crossdomain").val()) a.postMessage({
                            sender: "responsivefilemanager",
                            url: r,
                            field_id: t
                        }, "*");
                        else {
                            var i = jQuery("#" + t, a.document);
                            i.val(r).trigger("change"), "function" == typeof a.responsive_filemanager_callback && a.responsive_filemanager_callback(t), Q()
                        } else apply_any(r)
                },
                copy: function(e) {
                    d(e, "copy")
                },
                cut: function(e) {
                    d(e, "cut")
                },
                paste: function() {
                    f()
                },
                chmod: function(e) {
                    o(e)
                },
                edit_text_file: function(e) {
                    i(e)
                }
            },
            makeContextMenu: function() {
                var a = this;
                e.contextMenu({
                    selector: "figure:not(.back-directory), .list-view2 figure:not(.back-directory)",
                    autoHide: !0,
                    build: function(e) {
                        e.addClass("selected");
                        var t = {
                            callback: function(r, t) {
                                a.contextActions[r](e)
                            },
                            items: {}
                        };
                        return (e.find(".img-precontainer-mini .filetype").hasClass("png") || e.find(".img-precontainer-mini .filetype").hasClass("jpg") || e.find(".img-precontainer-mini .filetype").hasClass("jpeg")) && r && (t.items.edit_img = {
                            name: jQuery("#lang_edit_image").val(),
                            icon: "edit_img",
                            disabled: !1
                        }), e.hasClass("directory") && 0 != jQuery("#type_param").val() && (t.items.select = {
                            name: jQuery("#lang_select").val(),
                            icon: "",
                            disabled: !1
                        }), t.items.copy_url = {
                            name: jQuery("#lang_show_url").val(),
                            icon: "url",
                            disabled: !1
                        }, (e.find(".img-precontainer-mini .filetype").hasClass("zip") || e.find(".img-precontainer-mini .filetype").hasClass("tar") || e.find(".img-precontainer-mini .filetype").hasClass("gz")) && (t.items.unzip = {
                            name: jQuery("#lang_extract").val(),
                            icon: "extract",
                            disabled: !1
                        }), e.find(".img-precontainer-mini .filetype").hasClass("edit-text-file-allowed") && (t.items.edit_text_file = {
                            name: jQuery("#lang_edit_file").val(),
                            icon: "edit",
                            disabled: !1
                        }), e.hasClass("directory") || 1 != jQuery("#duplicate").val() || (t.items.duplicate = {
                            name: jQuery("#lang_duplicate").val(),
                            icon: "duplicate",
                            disabled: !1
                        }), e.hasClass("directory") || 1 != jQuery("#copy_cut_files_allowed").val() ? e.hasClass("directory") && 1 == jQuery("#copy_cut_dirs_allowed").val() && (t.items.copy = {
                            name: jQuery("#lang_copy").val(),
                            icon: "copy",
                            disabled: !1
                        }, t.items.cut = {
                            name: jQuery("#lang_cut").val(),
                            icon: "cut",
                            disabled: !1
                        }) : (t.items.copy = {
                            name: jQuery("#lang_copy").val(),
                            icon: "copy",
                            disabled: !1
                        }, t.items.cut = {
                            name: jQuery("#lang_cut").val(),
                            icon: "cut",
                            disabled: !1
                        }), 0 == jQuery("#clipboard").val() || e.hasClass("directory") || (t.items.paste = {
                            name: jQuery("#lang_paste_here").val(),
                            icon: "clipboard-apply",
                            disabled: !1
                        }), e.hasClass("directory") || 1 != jQuery("#chmod_files_allowed").val() ? e.hasClass("directory") && 1 == jQuery("#chmod_dirs_allowed").val() && (t.items.chmod = {
                            name: jQuery("#lang_file_permission").val(),
                            icon: "key",
                            disabled: !1
                        }) : t.items.chmod = {
                            name: jQuery("#lang_file_permission").val(),
                            icon: "key",
                            disabled: !1
                        }, t.items.sep = "----", t.items.info = {
                            name: "<strong>" + jQuery("#lang_file_info").val() + "</strong>",
                            disabled: !0
                        }, t.items.name = {
                            name: e.attr("data-name"),
                            icon: "label",
                            disabled: !0
                        }, "img" == e.attr("data-type") && (t.items.dimension = {
                            name: e.find(".img-dimension").html(),
                            icon: "dimension",
                            disabled: !0
                        }), "true" !== jQuery("#show_folder_size").val() && "true" !== jQuery("#show_folder_size").val() || (e.hasClass("directory") ? t.items.size = {
                            name: e.find(".file-size").html() + " - " + e.find(".nfiles").val() + " " + jQuery("#lang_files").val() + " - " + e.find(".nfolders").val() + " " + jQuery("#lang_folders").val(),
                            icon: "size",
                            disabled: !0
                        } : t.items.size = {
                            name: e.find(".file-size").html(),
                            icon: "size",
                            disabled: !0
                        }), t.items.date = {
                            name: e.find(".file-date").html(),
                            icon: "date",
                            disabled: !0
                        }, t
                    },
                    events: {
                        hide: function() {
                            jQuery("figure").removeClass("selected")
                        }
                    }
                }), jQuery(document).on("contextmenu", function(e) {
                    if (!jQuery(e.target).is("figure")) return !1
                })
            },
            bindGridEvents: function() {
                function a(e) {
                    window[e.attr("data-function")](e.attr("data-file"), jQuery("#field_id").val())
                }
                var r = jQuery("ul.grid");
                r.on("click", ".modalAV", function(a) {
                    var r = jQuery(this);
                    a.preventDefault();
                    var t = jQuery("#previewAV"),
                        n = jQuery(".body-preview");
                    t.removeData("modal"), t.modal({
                        backdrop: "static",
                        keyboard: !1
                    }), r.hasClass("audio") ? n.css("height", "80px") : n.css("height", "345px"), e.ajax({
                        url: r.attr("data-url"),
                        success: function(e) {
                            n.html(e)
                        }
                    })
                }), r.on("click", ".file-preview-btn", function(a) {
                    var r = jQuery(this);
                    a.preventDefault(), e.ajax({
                        url: r.attr("data-url"),
                        success: function(e) {
                            bootbox.modal(e, " " + r.parent().parent().parent().find(".name").val())
                        }
                    })
                }), r.on("click", ".preview", function() {
                    var e = jQuery(this);
                    return 0 == e.hasClass("disabled") && jQuery("#full-img").attr("src", decodeURIComponent(e.attr("data-url"))), !0
                }), r.on("click", ".rename-file", function() {
                    var a = jQuery(this),
                        r = a.parent().parent().parent(),
                        t = r.find("h4"),
                        n = e.trim(t.text());
                    bootbox.prompt(jQuery("#rename").val(), jQuery("#cancel").val(), jQuery("#ok").val(), function(e) {
                        null !== e && (e = _(e), e != n && g("rename_file", a.attr("data-path"), e, r, "apply_file_rename"))
                    }, n)
                }), r.on("click", ".rename-folder", function() {
                    var a = jQuery(this),
                        r = a.parent().parent().parent(),
                        t = r.find("h4"),
                        n = e.trim(t.text());
                    bootbox.prompt(jQuery("#rename").val(), jQuery("#cancel").val(), jQuery("#ok").val(), function(e) {
                        null !== e && (e = _(e).replace(".", ""), e != n && g("rename_folder", a.attr("data-path"), e, r, "apply_folder_rename"))
                    }, n)
                }), r.on("click", ".delete-file", function() {
                    var e = jQuery(this);
                    bootbox.confirm(e.attr("data-confirm"), jQuery("#cancel").val(), jQuery("#ok").val(), function(a) {
                        if (1 == a) {
                            g("delete_file", e.attr("data-path"), "", "", "");
                            var r = jQuery("#files_number");
                            r.text(parseInt(r.text()) - 1), e.parent().parent().parent().parent().remove()
                        }
                    })
                }), r.on("click", ".delete-folder", function() {
                    var e = jQuery(this);
                    bootbox.confirm(e.attr("data-confirm"), jQuery("#cancel").val(), jQuery("#ok").val(), function(a) {
                        if (1 == a) {
                            g("delete_folder", e.attr("data-path"), "", "", "");
                            var r = jQuery("#folders_number");
                            r.text(parseInt(r.text()) - 1), e.parent().parent().parent().remove()
                        }
                    })
                }), jQuery("ul.grid").on("click", ".link", function() {
                    a(jQuery(this))
                }), jQuery("ul.grid").on("click", "div.box", function(e) {
                    var r = jQuery(this).find(".link");
                    if (0 !== r.length) a(r);
                    else {
                        var t = jQuery(this).find(".folder-link");
                        0 !== t.length && (document.location = jQuery(t).prop("href"))
                    }
                })
            },
            makeFilters: function(a) {
                jQuery("#filter-input").on("keyup", function() {
                    jQuery(".filters label").removeClass("btn-inverse"), jQuery(".filters label").find("i").removeClass("icon-white"), jQuery("#ff-item-type-all").addClass("btn-inverse"), jQuery("#ff-item-type-all").find("i").addClass("icon-white");
                    var r = _(jQuery(this).val()).toLowerCase();
                    jQuery(this).val(r), a && I(function() {
                        jQuery("li", "ul.grid ").each(function() {
                            var e = jQuery(this);
                            "" != r && e.attr("data-name").toLowerCase().indexOf(r) == -1 ? e.hide(100) : e.show(100)
                        }), e.ajax({
                            url: "ajax_calls.php?action=filter&type=" + r
                        }).done(function(e) {
                            "" != e && bootbox.alert(e)
                        }), I(function() {
                            var e = 0 != jQuery("#descending").val();
                            b(e, "." + jQuery("#sort_by").val()), x()
                        }, 500)
                    }, 300)
                }).keypress(function(e) {
                    13 == e.which && jQuery("#filter").trigger("click")
                }), jQuery("#filter").on("click", function() {
                    var e = _(jQuery("#filter-input").val());
                    window.location.href = jQuery("#current_url").val() + "&filter=" + e
                })
            },
            makeUploader: function() {
                jQuery("#uploader-btn").on("click", function() {
                    var e = jQuery("#sub_folder").val() + jQuery("#fldr_value").val() + "/";
                    e = e.substring(0, e.length - 1), jQuery("#iframe-container").html(jQuery("<iframe />", {
                        name: "JUpload",
                        id: "uploader_frame",
                        src: "uploader/index.php?path=" + e,
                        frameborder: 0,
                        width: "100%",
                        height: 360
                    }))
                }), jQuery(".upload-btn").on("click", function() {
                    jQuery(".uploader").show(500)
                }), jQuery(".close-uploader").on("click", function() {
                    jQuery(".uploader").hide(500), setTimeout(function() {
                        window.location.href = jQuery("#refresh").attr("href") + "&" + (new Date).getTime()
                    }, 420)
                })
            },
            uploadURL: function() {
                jQuery("#uploadURL").on("click", function(a) {
                    a.preventDefault();
                    var r = jQuery("#url").val(),
                        t = jQuery("#cur_path").val(),
                        n = jQuery("#cur_dir_thumb").val();
                    show_animation(), e.ajax({
                        type: "POST",
                        url: "upload.php",
                        data: {
                            path: t,
                            path_thumb: n,
                            url: r
                        }
                    }).done(function(e) {
                        hide_animation(), jQuery("#url").val("")
                    }).fail(function(e) {
                        bootbox.alert(jQuery("#lang_error_upload").val()), hide_animation(), jQuery("#url").val("")
                    })
                })
            },
            makeSort: function(a) {
                jQuery("input[name=radio-sort]").on("click", function() {
                    var e = jQuery(this).attr("data-item"),
                        t = jQuery("#" + e),
                        n = jQuery(".filters label");
                    n.removeClass("btn-inverse"), n.find("i").removeClass("icon-white"), jQuery("#filter-input").val(""), t.addClass("btn-inverse"), t.find("i").addClass("icon-white"), "ff-item-type-all" == e ? (a ? jQuery(".grid li").show(300) : window.location.href = jQuery("#current_url").val() + "&sort_by=" + jQuery("#sort_by").val() + "&descending=" + (r ? 1 : 0), "undefined" != typeof Storage && localStorage.setItem("sort", "")) : jQuery(this).is(":checked") && (jQuery(".grid li").not("." + e).hide(300), jQuery(".grid li." + e).show(300), "undefined" != typeof Storage && localStorage.setItem("sort", e)), x()
                });
                var r = jQuery("#descending").val();
                jQuery(".sorter").on("click", function() {
                    var t = jQuery(this);
                    r = jQuery("#sort_by").val() !== t.attr("data-sort") || 0 == r, a ? (e.ajax({
                        url: "ajax_calls.php?action=sort&sort_by=" + t.attr("data-sort") + "&descending=" + (r ? 1 : 0)
                    }), b(r, "." + t.attr("data-sort")), jQuery(" a.sorter").removeClass("descending").removeClass("ascending"), r ? jQuery(".sort-" + t.attr("data-sort")).addClass("descending") : jQuery(".sort-" + t.attr("data-sort")).addClass("ascending"), jQuery("#sort_by").val(t.attr("data-sort")), jQuery("#descending").val(r ? 1 : 0), x()) : window.location.href = jQuery("#current_url").val() + "&sort_by=" + t.attr("data-sort") + "&descending=" + (r ? 1 : 0)
                })
            }
        };
    jQuery(document).ready(function() {
        if (C && U.makeContextMenu(), "undefined" != typeof Storage && 1 != e("#type_param").val() && 3 != e("#type_param").val()) {
            var r = localStorage.getItem("sort");
            if (r) {
                var i = jQuery("#" + r);
                i.addClass("btn-inverse"), i.find("i").addClass("icon-white"), jQuery(".grid li").not("." + r).hide(300), jQuery(".grid li." + r).show(300)
            }
        }
        if (jQuery("#full-img").on("click", function() {
                jQuery("#previewLightbox").lightbox("hide")
            }), jQuery("body").on("click", function() {
                jQuery(".tip-right").tooltip("hide")
            }), U.bindGridEvents(), parseInt(jQuery("#file_number").val()) > parseInt(jQuery("#file_number_limit_js").val())) var o = !1;
        else var o = !0;
        if (U.makeSort(o), U.makeFilters(o), U.uploadURL(), jQuery("#info").on("click", function() {
                bootbox.alert('<div class="text-center"><br/><img src="img/logo.png" alt="responsive filemanager"/><br/><br/><p><strong>RESPONSIVE filemanager v.' + k + '</strong><br/><a href="http://www.responsivefilemanager.com">responsivefilemanager.com</a></p><br/><p>Copyright © <a href="http://www.tecrail.com" alt="tecrail">Tecrail</a> - Alberto Peripolli. All rights reserved.</p><br/><p>License<br/><small><img alt="Creative Commons License" style="border-width:0" src="http://responsivefilemanager.com/license.php" /><br />This work is licensed under a <a rel="license" href="http://creativecommons.org/licenses/by-nc/3.0/">Creative Commons Attribution-NonCommercial 3.0 Unported License</a>.</small></p></div>')
            }), jQuery("#change_lang_btn").on("click", function() {
                l()
            }), U.makeUploader(), jQuery("body").on("keypress", function(e) {
                var a = String.fromCharCode(e.which);
                if ("'" == a || '"' == a || "\\" == a || "/" == a) return !1
            }), jQuery("ul.grid li figcaption").on("click", 'a[data-toggle="lightbox"]', function() {
                t(decodeURIComponent(jQuery(this).attr("data-url")))
            }), jQuery(".create-file-btn").on("click", function() {
                n()
            }), jQuery(".new-folder").on("click", function() {
                bootbox.prompt(jQuery("#insert_folder_name").val(), jQuery("#cancel").val(), jQuery("#ok").val(), function(a) {
                    if (null !== a) {
                        a = _(a).replace(".", "");
                        var r = jQuery("#sub_folder").val() + jQuery("#fldr_value").val();
                        e.ajax({
                            type: "POST",
                            url: "execute.php?action=create_folder",
                            data: {
                                path: r,
                                name: a
                            }
                        }).done(function(e) {
                            setTimeout(function() {
                                window.location.href = jQuery("#refresh").attr("href") + "&" + (new Date).getTime()
                            }, 300)
                        })
                    }
                })
            }), jQuery(".view-controller button").on("click", function() {
                var a = jQuery(this);
                jQuery(".view-controller button").removeClass("btn-inverse"), jQuery(".view-controller i").removeClass("icon-white"), a.addClass("btn-inverse"), a.find("i").addClass("icon-white"), e.ajax({
                    url: "ajax_calls.php?action=view&type=" + a.attr("data-value")
                }).done(function(e) {
                    "" != e && bootbox.alert(e)
                }), "undefined" != typeof jQuery("ul.grid")[0] && jQuery("ul.grid")[0] && (jQuery("ul.grid")[0].className = jQuery("ul.grid")[0].className.replace(/\blist-view.*?\b/g, "")), "undefined" != typeof jQuery(".sorter-container")[0] && jQuery(".sorter-container")[0] && (jQuery(".sorter-container")[0].className = jQuery(".sorter-container")[0].className.replace(/\blist-view.*?\b/g, ""));
                var r = a.attr("data-value");
                jQuery("#view").val(r), jQuery("ul.grid").addClass("list-view" + r), jQuery(".sorter-container").addClass("list-view" + r), a.attr("data-value") >= 1 ? v(14) : (jQuery("ul.grid li").css("width", 126), jQuery("ul.grid figure").css("width", 122)), x()
            }), a.touch ? (jQuery("#help").show(), jQuery(".box:not(.no-effect)").swipe({
                swipeLeft: m,
                swipeRight: m,
                threshold: 30
            })) : (jQuery(".tip").tooltip({
                placement: "bottom"
            }), jQuery(".tip-top").tooltip({
                placement: "top"
            }), jQuery(".tip-left").tooltip({
                placement: "left"
            }), jQuery(".tip-right").tooltip({
                placement: "right"
            }), jQuery("body").addClass("no-touch")), jQuery(".paste-here-btn").on("click", function() {
                0 == jQuery(this).hasClass("disabled") && f()
            }), jQuery(".clear-clipboard-btn").on("click", function() {
                0 == jQuery(this).hasClass("disabled") && s()
            }), !a.csstransforms) {
            var c = jQuery("figure");
            c.on("mouseover", function() {
                0 == jQuery("#view").val() && jQuery("#main-item-container").hasClass("no-effect-slide") === !1 && jQuery(this).find(".box:not(.no-effect)").animate({
                    top: "-26px"
                }, {
                    queue: !1,
                    duration: 300
                })
            }), c.on("mouseout", function() {
                0 == jQuery("#view").val() && jQuery(this).find(".box:not(.no-effect)").animate({
                    top: "0px"
                }, {
                    queue: !1,
                    duration: 300
                })
            })
        }
        x();
        jQuery(window).resize(function() {
            v(28)
        }), v(14), y(1 == jQuery("#clipboard").val() ? !0 : !1), jQuery("li.dir, li.file").draggable({
            distance: 20,
            cursor: "move",
            helper: function() {
                jQuery(this).find("figure").find(".box").css("top", "0px");
                var e = jQuery(this).clone().css("z-index", 1e3).find(".box").css("box-shadow", "none").css("-webkit-box-shadow", "none").parent().parent();
                return jQuery(this).addClass("selected"), e
            },
            start: function(e, a) {
                jQuery(a.helper).addClass("ui-draggable-helper"), 0 == jQuery("#view").val() && jQuery("#main-item-container").addClass("no-effect-slide")
            },
            stop: function() {
                jQuery(this).removeClass("selected"), 0 == jQuery("#view").val() && jQuery("#main-item-container").removeClass("no-effect-slide")
            }
        }), jQuery("li.dir,li.back").droppable({
            accept: "ul.grid li",
            activeClass: "ui-state-highlight",
            hoverClass: "ui-state-hover",
            drop: function(e, a) {
                p(a.draggable.find("figure"), jQuery(this).find("figure"))
            }
        }), jQuery(document).on("keyup", "#chmod_form #chmod_value", function() {
            u(!0)
        }), jQuery(document).on("change", "#chmod_form input", function() {
            u(!1)
        }), jQuery(document).on("focusout", "#chmod_form #chmod_value", function() {
            var e = jQuery("#chmod_form #chmod_value");
            null == e.val().match(/^[0-7]{3}$/) && (e.val(e.attr("data-def-value")), u(!0))
        })
    }), encodeURL = function(e) {
        for (var a = e.split("/"), r = 3; r < a.length; r++) a[r] = encodeURIComponent(a[r]);
        return a.join("/")
    }, apply = function(a, r) {
        var t;
        t = 1 == jQuery("#popup").val() ? window.opener : window.parent;
        var n = jQuery("#callback").val(),
            i = jQuery("#cur_dir").val(),
            l = jQuery("#subdir").val(),
            o = jQuery("#base_url").val(),
            u = a.substr(0, a.lastIndexOf(".")),
            c = a.split(".").pop();
        c = c.toLowerCase();
        var s = "",
            d = ["ogg", "mp3", "wav"],
            f = ["mp4", "ogg", "webm"];
        if (1 == jQuery("#ftp").val()) var p = encodeURL(jQuery("#ftp_base_url").val() + jQuery("#upload_dir").val() + jQuery("#fldr_value").val() + a);
        else var y = jQuery("#return_relative_url").val(),
            p = encodeURL((1 == y ? l : o + i) + a);
        if ("" != r)
            if (1 == jQuery("#crossdomain").val()) t.postMessage({
                sender: "responsivefilemanager",
                url: p,
                field_id: r
            }, "*");
            else {
                var v = jQuery("#" + r, t.document);
                v.val(p).trigger("change"), 0 == n ? "function" == typeof t.responsive_filemanager_callback && t.responsive_filemanager_callback(r) : "function" == typeof t[n] && t[n](r), Q()
            } else e.inArray(c, ext_img) > -1 ? (p = jQuery("#add_time_to_img").val() ? p + "?" + (new Date).getTime() : p, s = '<img src="' + p + '" alt="' + u + '" />') : e.inArray(c, f) > -1 ? s = '<video controls source src="' + p + '" type="video/' + c + '">' + u + "</video>" : e.inArray(c, d) > -1 ? ("mp3" == c && (c = "mpeg"), s = '<audio controls src="' + p + '" type="audio/' + c + '">' + u + "</audio>") : s = '<a href="' + p + '" title="' + u + '">' + u + "</a>", 1 == jQuery("#crossdomain").val() ? t.postMessage({
            sender: "responsivefilemanager",
            url: p,
            field_id: null,
            html: s
        }, "*") : parent.tinymce.majorVersion < 4 ? (parent.tinymce.activeEditor.execCommand("mceInsertContent", !1, s), parent.tinymce.activeEditor.windowManager.close(parent.tinymce.activeEditor.windowManager.params.mce_window_id)) : (parent.tinymce.activeEditor.insertContent(s), parent.tinymce.activeEditor.windowManager.close())
    }, apply_link = function(e, a) {
        if (1 == jQuery("#popup").val()) var r = window.opener;
        else var r = window.parent;
        var t = jQuery("#callback").val(),
            n = jQuery("#cur_dir").val();
        n = n.replace("\\", "/");
        var i = jQuery("#subdir").val();
        i = i.replace("\\", "/");
        var l = jQuery("#base_url").val();
        if (1 == jQuery("#ftp").val()) var o = encodeURL(jQuery("#ftp_base_url").val() + jQuery("#upload_dir").val() + jQuery("#fldr_value").val() + e);
        else var u = jQuery("#return_relative_url").val(),
            o = encodeURL((1 == u ? i : l + n) + e);
        if ("" != a)
            if (1 == jQuery("#crossdomain").val()) r.postMessage({
                sender: "responsivefilemanager",
                url: o,
                field_id: a
            }, "*");
            else {
                var c = jQuery("#" + a, r.document);
                c.val(o).trigger("change"), 0 == t ? "function" == typeof r.responsive_filemanager_callback && r.responsive_filemanager_callback(a) : "function" == typeof r[t] && r[t](a), Q()
            } else apply_any(o)
    }, apply_img = function(e, a) {
        var r;
        r = 1 == jQuery("#popup").val() ? window.opener : window.parent;
        var t = jQuery("#callback").val(),
            n = jQuery("#cur_dir").val();
        n = n.replace("\\", "/");
        var i = jQuery("#subdir").val();
        i = i.replace("\\", "/");
        var l = jQuery("#base_url").val();
        if (1 == jQuery("#ftp").val()) var o = encodeURL(jQuery("#ftp_base_url").val() + jQuery("#upload_dir").val() + jQuery("#fldr_value").val() + e);
        else var u = jQuery("#return_relative_url").val(),
            o = encodeURL((1 == u ? i : l + n) + e);
        if ("" != a)
            if (1 == jQuery("#crossdomain").val()) r.postMessage({
                sender: "responsivefilemanager",
                url: o,
                field_id: a
            }, "*");
            else {
                var c = jQuery("#" + a, r.document);
                c.val(o).trigger("change"), 0 == t ? "function" == typeof r.responsive_filemanager_callback && r.responsive_filemanager_callback(a) : "function" == typeof r[t] && r[t](a), Q()
            } else jQuery("#add_time_to_img").val() && (o = o + "?" + (new Date).getTime()), apply_any(o)
    }, apply_video = function(e, a) {
        var r;
        r = 1 == jQuery("#popup").val() ? window.opener : window.parent;
        var t = jQuery("#callback").val(),
            n = jQuery("#cur_dir").val();
        n = n.replace("\\", "/");
        var i = jQuery("#subdir").val();
        i = i.replace("\\", "/");
        var l = jQuery("#base_url").val();
        if (1 == jQuery("#ftp").val()) var o = encodeURL(jQuery("#ftp_base_url").val() + jQuery("#upload_dir").val() + jQuery("#fldr_value").val() + e);
        else var u = jQuery("#return_relative_url").val(),
            o = encodeURL((1 == u ? i : l + n) + e);
        if ("" != a)
            if (1 == jQuery("#crossdomain").val()) r.postMessage({
                sender: "responsivefilemanager",
                url: o,
                field_id: a
            }, "*");
            else {
                var c = jQuery("#" + a, r.document);
                c.val(o).trigger("change"), 0 == t ? "function" == typeof r.responsive_filemanager_callback && r.responsive_filemanager_callback(a) : "function" == typeof r[t] && r[t](a), Q()
            } else apply_any(o)
    }, apply_none = function(e) {
        var a = jQuery("ul.grid").find('li[data-name="' + e + '"] figcaption a');
        a[1].click(), jQuery(".tip-right").tooltip("hide")
    }, apply_any = function(e) {
        if (1 == jQuery("#crossdomain").val()) window.parent.postMessage({
            sender: "responsivefilemanager",
            url: e,
            field_id: null
        }, "*");
        else {
            var a = jQuery("#editor").val();
            if ("ckeditor" == a) {
                var r = j("CKEditorFuncNum");
                window.opener.CKEDITOR.tools.callFunction(r, e), window.close()
            } else parent.tinymce.majorVersion < 4 ? (parent.tinymce.activeEditor.windowManager.params.setUrl(e), parent.tinymce.activeEditor.windowManager.close(parent.tinymce.activeEditor.windowManager.params.mce_window_id)) : (parent.tinymce.activeEditor.windowManager.getParams().setUrl(e), parent.tinymce.activeEditor.windowManager.close())
        }
    }, apply_file_duplicate = function(e, a) {
        var r = e.parent().parent().parent().parent();
        r.after("<li class='" + r.attr("class") + "' data-name='" + r.attr("data-name") + "'>" + r.html() + "</li>");
        var t = r.next();
        apply_file_rename(t.find("figure"), a);
        var n = t.find(".download-form"),
            i = "form" + (new Date).getTime();
        n.attr("id", i), n.find(".tip-right").attr("onclick", "jQuery('#" + i + "').submit();")
    }, apply_file_rename = function(e, a) {
        var r;
        e.attr("data-name", a), e.parent().attr("data-name", a), e.find("h4").text(a);
        var t = e.find("a.link");
        r = t.attr("data-file");
        var n = r.substring(r.lastIndexOf("/") + 1),
            i = r.substring(r.lastIndexOf(".") + 1);
        t.each(function() {
            jQuery(this).attr("data-file", encodeURIComponent(a + "." + i))
        }), e.find("img").each(function() {
            var e = jQuery(this).attr("src");
            jQuery(this).attr("src", e.replace(n, a + "." + i) + "?time=" + (new Date).getTime()), jQuery(this).attr("alt", a + " thumbnails")
        });
        var l = e.find("a.preview");
        r = l.attr("data-url"), "undefined" != typeof r && r && l.attr("data-url", r.replace(encodeURIComponent(n), encodeURIComponent(a + "." + i))), e.parent().attr("data-name", a + "." + i), e.attr("data-name", a + "." + i), e.find(".name_download").val(a + "." + i);
        var o = e.find("a.rename-file"),
            u = e.find("a.delete-file"),
            c = o.attr("data-path"),
            s = c.replace(n, a + "." + i);
        o.attr("data-path", s), u.attr("data-path", s)
    }, apply_folder_rename = function(e, a) {
        e.attr("data-name", a), e.find("figure").attr("data-name", a);
        var r = e.find("h4").find("a").text();
        e.find("h4 > a").text(a);
        var t = e.find(".folder-link"),
            n = t.attr("href"),
            i = jQuery("#fldr_value").val(),
            l = n.replace("fldr=" + i + encodeURIComponent(r), "fldr=" + i + encodeURIComponent(a));
        t.each(function() {
            jQuery(this).attr("href", l)
        });
        var o = e.find("a.delete-folder"),
            u = e.find("a.rename-folder"),
            c = u.attr("data-path"),
            s = c.lastIndexOf("/"),
            d = c.substr(0, s + 1) + a;
        o.attr("data-path", d), u.attr("data-path", d)
    }, show_animation = function() {
        jQuery("#loading_container").css("display", "block"), jQuery("#loading").css("opacity", ".7")
    }, hide_animation = function() {
        jQuery("#loading_container").fadeOut()
    }
}(jQuery, Modernizr, image_editor);