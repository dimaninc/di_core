class TargetInside
    $targetType = null
    $targetId = null

    constructor: (opts) ->
        @opts = $.extend
            types: []
            targets: []
            emptyTitles:
                type: '[ Не выбрано ]'
                id: '[ Не выбрано ]'
            selected:
                type: null
                id: null
        , opts

        @setupSelects()

    emptyOptionsNeeded: ->
        !!@opts.emptyTitles.type

    createOption: (title, id) ->
        $ '<option value="{0}">{1}</option>'.format id, title

    setupSelects: ->
        self = @

        $targetType = $ 'select[name="target_type"],input[name="target_type"]'
        $targetId = $ 'select[name="target_id"],input[name="target_id"]'

        if $targetType.is 'input'
            $s = $ '<select name="target_type" id="target_type"></select>'
            $targetType.replaceWith $s
            $targetType = $s
        else
            $targetType.find 'option'
            .remove()

        if $targetId.is 'input'
            $s = $ '<select name="target_id" id="target_id"></select>'
            $targetId.replaceWith $s
            $targetId = $s

        if @emptyOptionsNeeded()
            $targetType.append @createOption @opts.emptyTitles.type, 0

        $.each @opts.types, (id, title) ->
            $targetType.append self.createOption title, id
            self.opts.selected.type = id * 1 unless self.opts.selected.type * 1
            true

        $targetType
        .val @opts.selected.type
        .on 'focus blur change click keyup', ->
            self.loadTargets @value
            self.opts.selected.type = @value if @value * 1
            true

        $targetId
        .on 'focus blur change click keyup', ->
            self.opts.selected.id = @value if @value * 1
            true

        @loadTargets()

        @

    loadTargets: (type = @opts.selected.type) ->
        return @ if @opts.selected.type * 1 is @value * 1 or not type

        self = @

        $targetId.find 'option'
        .remove()

        if @emptyOptionsNeeded()
            $targetId.append @createOption @opts.emptyTitles.id, 0

        $.each @opts.targets[type], (id, ar) ->
            $targetId.append self.createOption ar.title, ar.id

        $targetId.val @opts.selected.id if @opts.selected.type * 1 is $targetType.val() * 1

        @