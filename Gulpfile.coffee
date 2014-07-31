gulp = require('gulp')
coffee = require('gulp-coffee')
coffeelint = require('gulp-coffeelint')
concat = require('gulp-concat')
less = require('gulp-less')
cssmin = require('gulp-cssmin')
argv = require('yargs')
check = require('gulp-if')
uglify = require('gulp-uglify')
rimraf = require('gulp-rimraf')

gulp.task 'styles-vendors', ->
  gulp.src ['assets/bower/select2/{select2,select2-bootstrap}.css', 'assets/bower/bootstrap-datepicker/css/datepicker3.css']
    .pipe cssmin()
    .pipe concat('vendors.min.css')
    .pipe gulp.dest('assets/css')

gulp.task 'styles', ['styles-vendors'], ->
  gulp.src 'assets/less/**/*.less'
    .pipe less()
    .pipe cssmin()
    .pipe gulp.dest('assets/css')

gulp.task 'scripts-vendors', ->
  gulp.src ['assets/bower/select2/select2.js', 'assets/bower/bootstrap/js/{tab,transition,tooltip}.js', 'assets/bower/bootstrap-datepicker/js/bootstrap-datepicker.js']
    .pipe uglify()
    .pipe concat('vendors.min.js')
    .pipe gulp.dest('assets/js')

gulp.task 'scripts', ['lint', 'scripts-vendors'], ->
  gulp.src 'assets/coffee/**/*.coffee'
    .pipe coffee({bare: true})
    .pipe check(argv.production, uglify())
    .pipe gulp.dest('assets/js')

gulp.task 'lint', ->
  gulp.src 'assets/coffee/**/*.coffee'
    .pipe coffeelint('coffeelint.json')
    .pipe coffeelint.reporter()
    .pipe coffeelint.reporter('fail')

gulp.task 'fonts', ->
  gulp.src 'assets/bower/bootstrap/fonts/*'
    .pipe gulp.dest('assets/fonts')

gulp.task 'clean', ->
  gulp.src ['assets/css/*', 'assets/js/*', 'assets/fonts'], {read: false}
    .pipe rimraf()

gulp.task 'watch', ['styles', 'scripts'], ->
  gulp.watch ['assets/coffee/**/*.coffee'], ['scripts']
  gulp.watch ['assets/less/**/*.less'], ['styles']

gulp.task 'default', ['styles', 'scripts', 'fonts']
