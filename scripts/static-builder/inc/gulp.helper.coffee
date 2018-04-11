Helper =
    workFolder: './'
    htDocsFolder: 'htdocs'

    setHtDocsFolder: (folder) ->
        @htDocsFolder = folder
        @

    getHtDocsFolder: -> @htDocsFolder

    extend: ->
        for i in [1..arguments.length]
            for key of arguments[i]
                if arguments[i].hasOwnProperty(key)
                    if typeof arguments[0][key] is 'object' and typeof arguments[i][key] is 'object'
                        @extend arguments[0][key], arguments[i][key]
                    else
                        arguments[0][key] = arguments[i][key]
        arguments[0]

    setWorkFolder: (@workFolder) ->
        @

    req: (module) ->
        require @workFolder + '/node_modules/' + module

    getRootFolder: ->
        './'

    getVersionFile: ->
        '../_cfg/lib/diStaticBuild.php'

    fullPath: (path) ->
        neg = ''
        if path.substr(0, 1) is '!'
            neg = '!'
            path = path.substr(1)
        neg + @getRootFolder() + path

    tryDone: (tasksDone, tasksTotal, done) ->
        done() if tasksDone is tasksTotal
        @

    deleteFolderRecursive: (folder, excludesList = []) ->
        fs = require 'fs' unless fs
        path = require 'path' unless path
        if fs.existsSync folder
            fs.readdirSync(folder).forEach (file, index) =>
                curPath = path.join folder + '/' + file
                return if curPath in excludesList
                if fs.lstatSync(curPath).isDirectory()
                    @deleteFolderRecursive curPath
                else
                    fs.unlinkSync curPath
                return
            fs.rmdirSync folder
        @

    copyCoreAssets: (gulp, done) ->
        console.log 'Copying CSS'
        gulp
        .src ['../vendor/dimaninc/di_core/css/**/*']
        .pipe gulp.dest '../' + @getHtDocsFolder() + '/assets/styles/_core/'

        console.log 'Copying Fonts'
        gulp
        .src ['../vendor/dimaninc/di_core/fonts/**/*']
        .pipe gulp.dest '../' + @getHtDocsFolder() + '/assets/fonts/'

        console.log 'Copying Images'
        gulp
        .src ['../vendor/dimaninc/di_core/i/**/*']
        .pipe gulp.dest '../' + @getHtDocsFolder() + '/assets/images/_core/'

        console.log 'Copying JS'
        gulp
        .src ['../vendor/dimaninc/di_core/js/**/*']
        .pipe gulp.dest '../' + @getHtDocsFolder() + '/assets/js/_core/'

        console.log 'Copying Vendor libs'
        gulp
        .src ['../vendor/dimaninc/di_core/vendor/**/*']
        .pipe gulp.dest '../' + @getHtDocsFolder() + '/assets/vendor/'

        done()
        @

    getFolders: ->
        [
            '_admin/_inc/cache'
            '_cfg/cache'
            'db/dump'
            @getHtDocsFolder() + '/assets/fonts'
            @getHtDocsFolder() + '/assets/images/_core'
            @getHtDocsFolder() + '/assets/js/_core'
            @getHtDocsFolder() + '/assets/styles/_core'
            @getHtDocsFolder() + '/uploads'
            'log'
            'log/db'
            'log/debug'
        ]

    createFolders: (done) ->
        mkdirp = @req 'mkdirp' unless mkdirp
        folders = @getFolders().map (f) -> '../' + f
        tasksTotal = folders.length
        tasksDone = 0
        for folder in folders
            do (folder) ->
                mkdirp folder, mode: 0o777, (err) ->
                    if err
                        console.error err
                    else
                        console.log folder, 'created'
                    Helper.tryDone ++tasksDone, tasksTotal, done
        @

    writeVersionFile: ->
        fs = require 'fs' unless fs
        fs.writeFileSync @getRootFolder() + @getVersionFile(),
            '<?php\nclass diStaticBuild\n{\n    const VERSION = ' + (new Date()).getTime() + ';\n}'
        @

    assignBasicTasksToGulp: (gulp) ->
        # create version timestamp php class
        gulp.task 'version', (done) ->
            Helper.writeVersionFile()
            done()

        # create initial folders
        gulp.task 'create-folders', (done) ->
            Helper.createFolders done

        # copy core assets to htdocs
        gulp.task 'copy-core-assets', (done) ->
            Helper.copyCoreAssets gulp, done

        # init project
        gulp.task 'init', gulp.series(
            'create-folders'
            'copy-core-assets'
        )

        @

    assignLessTaskToGulp: (gulp, opts = {}) ->
        less = @req 'gulp-less' unless less
        opts = @extend {fn: null, buildFolder: null, taskName: 'less'}, opts
        gulp.task opts.taskName, (done) =>
            gulp.src @fullPath opts.fn
            .pipe less()
            .on 'error', console.log
            .pipe gulp.dest @fullPath opts.buildFolder
            .on 'end', -> done()
        @

    assignStylusTaskToGulp: (gulp, opts = {}) ->
        stylus = @req 'gulp-stylus' unless stylus
        nib = @req 'nib' unless nib
        opts = @extend {fn: null, buildFolder: null, taskName: 'stylus'}, opts
        gulp.task opts.taskName, (done) =>
            gulp.src @fullPath opts.fn
            .pipe stylus use: nib(), 'include css': true
            .on 'error', console.log
            .pipe gulp.dest @fullPath opts.buildFolder
            .on 'end', -> done()
        @

    assignPngSpritesTaskToGulp: (gulp, opts = {}) ->
        spriteSmith = @req 'gulp.spritesmith' unless spriteSmith
        opts = @extend {mask: null, imgName: null, cssName: null, cssFormat: 'stylus', imgFolder: null, cssFolder: null, taskName: 'stylus-sprite'}, opts
        gulp.task opts.taskName, (done) =>
            spriteData = gulp.src @fullPath opts.mask
            .pipe spriteSmith
                imgName: opts.imgName
                cssName: opts.cssName
                cssFormat: opts.cssFormat
                algorithm: 'binary-tree'
                cssTemplate: (data) ->
                    timestamp = (new Date).getTime()
                    template = "$sprite-timestamp = #{timestamp}\n"

                    for item in data.items
                        template += "$sprite-#{item.name} = #{item.px.offset_x} #{item.px.offset_y} #{item.px.width} #{item.px.height}\n"

                    template
            .on 'error', console.log
            .on 'end', -> done()
            spriteData.img.pipe gulp.dest @fullPath opts.imgFolder
            spriteData.css.pipe gulp.dest @fullPath opts.cssFolder
        @

    assignCssConcatTaskToGulp: (gulp, opts = {}) ->
        concat = @req 'gulp-concat' unless concat
        opts = @extend {files: [], output: null, taskName: 'css-concat'}, opts
        gulp.task opts.taskName, (done) =>
            gulp.src opts.files.map (f) => @fullPath f
            .pipe concat opts.output
            .on 'error', console.log
            .pipe gulp.dest @fullPath @getRootFolder()
            .on 'end', -> done()
        @

    cleanCoffeeBuildDirectory: (folder) ->
        @deleteFolderRecursive @fullPath folder
        @

    assignCoffeeTaskToGulp: (gulp, opts = {}) ->
        coffee = @req 'gulp-coffee' unless coffee
        opts = @extend {folder: null, mask: null, jsBuildFolder: null, cleanBefore: false, taskName: 'coffee'}, opts
        gulp.task opts.taskName, (done) =>
            @cleanCoffeeBuildDirectory opts.jsBuildFolder if opts.cleanBefore
            gulp.src @fullPath opts.folder + opts.mask
            .pipe coffee bare: true
            .pipe gulp.dest @fullPath opts.jsBuildFolder
            .on 'end', -> done()
        @

module.exports = Helper
