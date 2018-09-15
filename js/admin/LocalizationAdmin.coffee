class LocalizationAdmin
    constructor: ->
        @setupForm()
        .setupList()

    setupForm: ->
        @setupAutoHeight()

    setupList: ->
        @setupExport()

    setupAutoHeight: ->
        setTimeout =>
            $ '.diadminform-row'
            .filter '[data-field$="value"]'
            .find 'textarea'
            .autoHeight()
        , 100
        @

    setupExport: ->
        $ '.filter-block [name="export"]'
        .on 'click', ->
            $t = $ '.dinicetable'
            $cb = $t.find 'tr td.id .checked, tr td.id input:checkbox:checked'
            linesAr = []
            names = []
            $out = $ '.export-block'

            if $out.length and $out.is ':visible'
                $out.hide()
                return false

            unless $cb.length
                alert 'Выберите хотя бы один Токен'
                return false

            $cb.each ->
                valuesAr = []
                $td = $(@).parent()
                while $td = $td.next 'td:eq(0)'
                    break if $td.hasClass 'btn'
                    val = $td.find('[data-purpose="orig"]').html()
                    val = $td.html() if val is undefined or val is null
                    val = val.replace(/'/g, '\\\'').replace(/"/g, '\\\"')
                    names.push val if $td.data('field') is 'name'
                    valuesAr.push val
                s = '$this->getDb()->q("INSERT IGNORE INTO `' + $t.data('table') +
                    '`(`name`,`value`,`en_value`,`de_value`,`it_value`,`es_value`,`fr_value`)\n' +
                    '\u0009\u0009\u0009VALUES(\'' + valuesAr.join('\',\'') + '\');' + '");'
                linesAr.push s
                true

            unless $out.length
                $out = $ '<div class="export-block"><textarea></textarea></div>'
                .insertAfter $(@).parent()

            $out.show().find('textarea').val names.map((n) => "'#{n}',").concat(linesAr).join '\n'

            false
        @
