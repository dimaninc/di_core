Helper =
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
        return

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
        mkdirp = require 'mkdirp' unless mkdirp
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

    writeVersionFile: ->
        fs = require 'fs' unless fs
        fs.writeFileSync @getRootFolder() + @getVersionFile(),
            '<?php\nclass diStaticBuild\n{\n    const VERSION = ' + (new Date()).getTime() + ';\n}'

module.exports = Helper
