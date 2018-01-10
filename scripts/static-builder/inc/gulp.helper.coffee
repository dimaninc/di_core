Helper =
    workFolder: './'

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
            fs.readdirSync(folder).forEach (file, index) ->
                curPath = path.join folder + '/' + file
                return if curPath in excludesList
                if fs.lstatSync(curPath).isDirectory()
                    deleteFolderRecursive curPath
                else
                    fs.unlinkSync curPath
                return
            fs.rmdirSync folder
        @

    copyCoreAssets: (gulp, done) ->
        console.log 'Copying CSS'
        gulp
        .src ['../vendor/dimaninc/di_core/css/**/*']
        .pipe gulp.dest '../htdocs/assets/styles/_core/'

        console.log 'Copying Fonts'
        gulp
        .src ['../vendor/dimaninc/di_core/fonts/**/*']
        .pipe gulp.dest '../htdocs/assets/fonts/'

        console.log 'Copying Images'
        gulp
        .src ['../vendor/dimaninc/di_core/i/**/*']
        .pipe gulp.dest '../htdocs/assets/images/_core/'

        console.log 'Copying JS'
        gulp
        .src ['../vendor/dimaninc/di_core/js/**/*']
        .pipe gulp.dest '../htdocs/assets/js/_core/'

        console.log 'Copying Vendor libs'
        gulp
        .src ['../vendor/dimaninc/di_core/vendor/**/*']
        .pipe gulp.dest '../htdocs/assets/vendor/'

        done()
        @

    getFolders: ->
        [
            '_admin/_inc/cache'
            '_cfg/cache'
            'db/dump'
            'htdocs/assets/fonts'
            'htdocs/assets/images/_core'
            'htdocs/assets/js/_core'
            'htdocs/assets/styles/_core'
            'htdocs/uploads'
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

    assignLessTaskToGulp: (gulp, opts = fn: null, buildFolder: null) ->
        less = @req 'gulp-less' unless less
        gulp.task 'less', (done) =>
            gulp.src @fullPath opts.fn
            .pipe less()
            .on 'error', console.log
            .pipe gulp.dest @fullPath opts.buildFolder
            .on 'end', -> done()
        @

    assignStylusTaskToGulp: (gulp, opts = fn: null, buildFolder: null) ->
        stylus = @req 'gulp-stylus' unless stylus
        nib = @req 'nib' unless nib
        gulp.task 'stylus', (done) =>
            gulp.src @fullPath opts.fn
            .pipe stylus use: nib(), 'include css': true
            .on 'error', console.log
            .pipe gulp.dest @fullPath opts.buildFolder
            .on 'end', -> done()
        @

    assignPngSpritesTaskToGulp: (gulp, opts = mask: null, imgName: null, cssName: null, cssFormat: 'stylus', imgFolder: null, cssFolder: null) ->
        spriteSmith = @req 'gulp.spritesmith' unless spriteSmith
        gulp.task 'stylus-sprite', (done) =>
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

    assignCssConcatTaskToGulp: (gulp, opts = files: [], output: null) ->
        concat = @req 'gulp-concat' unless concat
        gulp.task 'css-concat', (done) =>
            gulp.src opts.files.map (f) => @fullPath f
            .pipe concat opts.output
            .on 'error', console.log
            .pipe gulp.dest @fullPath @getRootFolder()
            .on 'end', -> done()
        @

module.exports = Helper
