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
            lines = []
            rawLines = []
            names = []
            $out = $ '.export-block'

            if $out.length and $out.is ':visible'
                $out.hide()
                return false

            unless $cb.length
                alert 'Выберите хотя бы один Токен'
                return false

            $cb.each ->
                fields = []
                values = []
                $td = $(@).parent()
                while $td = $td.next 'td:eq(0)'
                    break if $td.hasClass 'btn'
                    field = $td.data('field')
                    $e = $td.find('[data-purpose="orig"]')
                    val = $e.data('orig-value') or $e.html()
                    val = $td.data('orig-value') or $td.html() if val is undefined or val is null
                    val = val.replace(/'/g, '\\\'').replace(/"/g, '\\\"')
                    names.push val if field is 'name'
                    fields.push field
                    values.push val
                q = """INSERT IGNORE INTO `#{$t.data('table')}`(`#{fields.join('`,`')}`)\n\u0009\u0009\u0009VALUES('#{values.join('\',\'')}');"""
                s = """$this->getDb()->q("#{q}");"""
                lines.push s
                rawLines.push q
                true

            unless $out.length
                $out = $('<div class="export-block"><textarea></textarea></div>').insertAfter $(@).parent()

            text = names.map((n) => "'#{n}',").concat([''], lines, [''], rawLines).join '\n'
            $out.show().find('textarea').val text

            false
        @
