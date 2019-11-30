Helper =
    workFolder: './'
    htDocsFolder: 'htdocs'
    coreLocation: 'beyond'
    coreFolders:
        beyond: '../vendor/dimaninc/di_core/'
        inner: '../_core/'
    masks:
        css: '**/**/*.css'
        stylus: '**/*.styl'
        sass: '**/*.scss'
        less: '**/*.less'
        sprite: 'images/sprite-src/**/*.png'
        js: '**/**/*.js'
        coffee: '**/**/*.coffee'
        react: '**/**/*.jsx'
    folders:
        css: 'css/'
        stylus: 'css/admin/stylus/'
        js: 'js/'
    copyGroups: {}

    setHtDocsFolder: (@htDocsFolder) -> @
    getHtDocsFolder: -> @htDocsFolder
    setCoreLocation: (@coreLocation) -> @
    getCoreLocation: -> @coreLocation
    getCoreFolder: -> @coreFolders[@coreLocation]
    setWorkFolder: (@workFolder) -> @
    getRootFolder: -> './'
    getVersionFile: -> '../_cfg/lib/diStaticBuild.php'

    extend: ->
        for i in [1..arguments.length]
            for key of arguments[i]
                if arguments[i].hasOwnProperty(key)
                    if typeof arguments[0][key]? is 'object' and typeof arguments[i][key]? is 'object'
                        @extend arguments[0][key], arguments[i][key]
                    else
                        arguments[0][key] = arguments[i][key]
        arguments[0]

    req: (module) -> require @workFolder + '/node_modules/' + module

    fullPath: (path) ->
        if typeof path is 'object'
            for k, v of path
                path[k] = @fullPath v
            return path
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
        .src ['../vendor/dimaninc/di_core/css/**/*', '!../vendor/dimaninc/di_core/css/**/*.styl']
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
            do (folder) =>
                mkdirp folder, 0o777, (err) =>
                    if err
                        console.error err
                    else
                        console.log folder, 'created'
                    @tryDone ++tasksDone, tasksTotal, done
        @

    writeVersionFile: ->
        fs = require 'fs' unless fs
        fs.writeFileSync @getRootFolder() + @getVersionFile(),
            '<?php\nclass diStaticBuild\n{\n    const VERSION = ' + (new Date()).getTime() + ';\n}'
        @

    assignBasicTasksToGulp: (gulp) ->
        # create version timestamp php class
        gulp.task 'version', (done) =>
            @writeVersionFile()
            done()

        # create initial folders
        gulp.task 'create-folders', (done) => @createFolders done

        # copy core assets to htdocs
        gulp.task 'copy-core-assets', (done) => @copyCoreAssets gulp, done

        # init project
        gulp.task 'init', gulp.series(
            'create-folders'
            'copy-core-assets'
        )

        @

    assignLessTaskToGulp: (gulp, opts = {}) ->
        less = @req 'gulp-less' unless less
        opts = @extend {fn: null, buildFolder: null, postCss: false, taskName: 'less'}, opts
        gulp.task opts.taskName, (done) =>
            l = gulp.src @fullPath opts.fn
            .pipe less()

            if opts.postCss
                postcss  = @req 'gulp-postcss'
                l = l.pipe postcss [
                    @req 'precss'
                    @req 'postcss-cssnext'
                    @req 'cssnano'
                ]

            l.on 'error', console.log
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

    assignSassTaskToGulp: (gulp, opts = {}) ->
        unless sass
            sass = @req 'gulp-sass'
            sass.compiler = @req 'node-sass'
        opts = @extend {fn: null, buildFolder: null, taskName: 'sass'}, opts
        gulp.task opts.taskName, (done) =>
            gulp.src @fullPath opts.fn
                .pipe sass().on 'error', sass.logError
                #.on 'error', console.log
                .pipe gulp.dest @fullPath opts.buildFolder
                .on 'end', -> done()
        @

    assignImageMinTaskToGulp: (gulp, opts = {}) ->
        imagemin = @req 'gulp-imagemin' unless imagemin
        opts = @extend
            mask: null
            outputFolder: null
            taskName: 'imagemin'
            gif: null # gif settings
            jpeg: null # jpeg settings
            png: null # png settings
            svg: null # svg settings
        , opts
        compressOpts = []
        compressOpts.push imagemin.gifsicle opts.gif if opts.gif
        compressOpts.push imagemin.jpegtran opts.jpeg if opts.jpeg
        compressOpts.push imagemin.optipng opts.png if opts.png
        compressOpts.push imagemin.svgo opts.svg if opts.svg
        gulp.task opts.taskName, (done) =>
            gulp.src @fullPath opts.mask
                .pipe if compressOpts.length then imagemin(compressOpts) else imagemin()
                .on 'error', console.log
                .pipe gulp.dest @fullPath opts.outputFolder
                .on 'end', -> done()
        @

    assignPngSpritesTaskToGulp: (gulp, opts = {}) ->
        spriteSmith = @req 'gulp.spritesmith' unless spriteSmith
        opts = @extend
            taskName: 'stylus-sprite'
            imgFolder: null
            cssFolder: null
            mask: null
            imgName: null
            cssName: null
            cssFormat: 'stylus'
            algorithm: 'binary-tree'
            webp: false
            #imgOpts: {quality: 75}
            cssTemplate: (data) ->
                timestamp = (new Date).getTime()
                template = "$sprite-timestamp = #{timestamp}\n"
                for item in data.items
                    template += "$sprite-#{item.name} = #{item.px.offset_x} #{item.px.offset_y} #{item.px.width} #{item.px.height}\n"
                template
        , opts
        taskName = opts.taskName
        mask = opts.mask
        imgFolder = opts.imgFolder
        cssFolder = opts.cssFolder
        webpOpts = opts.webp
        delete opts.taskName
        delete opts.mask
        delete opts.cssFolder
        delete opts.imgFolder
        delete opts.webp
        gulp.task taskName, (done) =>
            spriteData = gulp.src @fullPath mask
            .pipe spriteSmith opts
            .on 'error', console.log
            .on 'end', -> done()
            spriteData.img.pipe gulp.dest @fullPath imgFolder
            spriteData.css.pipe gulp.dest @fullPath cssFolder

            if webpOpts
                webp = @req 'gulp-webp' unless webp
                rename = @req 'gulp-rename' unless rename
                gulp
                .src(imgFolder + opts.imgName)
                .pipe webp()
                .pipe rename suffix: '.png'
                .pipe gulp.dest(imgFolder)
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

    assignCssMinTaskToGulp: (gulp, opts = {}) ->
        csso = @req 'gulp-csso' unless csso
        rename = @req 'gulp-rename' unless rename
        opts = @extend {input: null, outputFolder: null, taskName: 'css-min', options: {}}, opts
        gulp.task opts.taskName, (done) =>
            gulp.src @fullPath opts.input
            .pipe csso opts.options
            .on 'error', console.log
            .pipe rename suffix: '.min'
            .pipe gulp.dest @fullPath opts.outputFolder
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

    assignEs6TaskToGulp: (gulp, opts = {}) ->
        babel = @req 'gulp-babel' unless babel
        sourcemaps = @req 'gulp-sourcemaps' unless sourcemaps
        opts = @extend {folder: null, mask: null, jsBuildFolder: null, taskName: 'es6'}, opts
        gulp.task opts.taskName, (done) =>
            gulp.src @fullPath opts.folder + opts.mask
            .pipe sourcemaps.init()
            .pipe babel compact: false, presets: ['@babel/env']
            .pipe sourcemaps.write '.'
            .pipe gulp.dest @fullPath opts.jsBuildFolder
            .on 'end', -> done()
        @

    assignJavascriptConcatTaskToGulp: (gulp, opts = {}) ->
        concat = @req 'gulp-concat' unless concat
        opts = @extend {files: [], output: null, taskName: 'js-concat'}, opts
        gulp.task opts.taskName, (done) =>
            gulp.src opts.files.map (f) => @fullPath f
            .pipe concat opts.output
            .on 'error', console.log
            .pipe gulp.dest @fullPath @getRootFolder()
            .on 'end', -> done()
        @

    assignJavascriptMinTaskToGulp: (gulp, opts = {}) ->
        uglify = @req 'gulp-uglify' unless uglify
        rename = @req 'gulp-rename' unless rename
        opts = @extend {input: null, outputFolder: null, taskName: 'js-min'}, opts
        gulp.task opts.taskName, (done) =>
            gulp.src @fullPath opts.input
            .pipe uglify()
            .on 'error', console.log
            .pipe rename suffix: '.min'
            .pipe gulp.dest @fullPath opts.outputFolder
            .on 'end', -> done()
        @

    addSimpleCopyTaskToGulp: (gulp, opts = {}) ->
        opts = @extend {groupId: null, files: [], baseFolder: null, destFolder: null, done: null}, opts

        unless @copyGroups[opts.groupId]?
            @copyGroups[opts.groupId] =
                total: 0
                done: 0
        @copyGroups[opts.groupId].total++

        gulp.src opts.files, base: opts.baseFolder
        .on 'error', console.log
        .pipe gulp.dest opts.destFolder
        .on 'end', => @tryDone ++@copyGroups[opts.groupId].done, @copyGroups[opts.groupId].total, opts.done
        @

    assignBowerFilesTaskToGulp: (gulp, opts = {}) ->
        bower = @req 'gulp-bower' unless bower
        opts = @extend {outputFolder: null, taskName: 'bower-files'}, opts
        gulp.task opts.taskName, (done) ->
            bower interactive: true
            .pipe gulp.dest opts.outputFolder
            .on 'end', -> done()
        @

    assignAdminStylusTaskToGulp: (gulp) ->
        watch =
            'admin-stylus':
                mask: @getCoreFolder() + @folders.stylus + Helper.masks.stylus
                hasProcess: true
            'admin-css':
                mask: @getCoreFolder() + @folders.css + Helper.masks.css
            'admin-js':
                mask: @getCoreFolder() + @folders.js + Helper.masks.js

        # main + login
        @assignStylusTaskToGulp gulp,
            taskName: 'admin-stylus'
            fn: [
                @getCoreFolder() + @folders.stylus + 'admin.styl'
                @getCoreFolder() + @folders.stylus + 'login.styl'
            ]
            buildFolder: @getCoreFolder() + 'css/admin/'

        # watch
        gulp.task 'admin-assets-watch', (done) ->
            for process of watch
                do (process, mask = watch[process].mask, hasProcess = watch[process].hasProcess) ->
                    if hasProcess
                        task = gulp.series process, 'copy-core-assets'
                    else
                        task = gulp.series 'copy-core-assets'
                    gulp.watch mask, task
                    true
            done()
        @

module.exports = Helper
