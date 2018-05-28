var gulp = require('gulp');
var beep = require('beepbeep'); // not working
var plumber = require('gulp-plumber');
var sourcemaps = require('gulp-sourcemaps');
var sass = require('gulp-sass');
var autoprefixer = require('gulp-autoprefixer');
var cssnano = require('gulp-cssnano');
var concat = require('gulp-concat');
var uglify = require('gulp-uglify');
var babel = require('gulp-babel');

// error function for plumber. prevents having to start gulp watch on error
var onError = function (err) {
    console.log(err);
    beep(3); // not working
    this.emit('end');
};

var scssSrc = '../Src/prebuild/scss/';
var jsSrc = '../Src/prebuild/js/';
var cssDest = '../Src/public/css';
var jsDest = '../Src/public/js';
var jsFile = 'scripts.min.js'; // use this only if concatenating all js with .pipe(concat(jsFile))

gulp.task('sass', function() {
    return gulp.src(scssSrc+'*.scss')
        .pipe(plumber({ errorHandler: onError }))
        .pipe(sourcemaps.init())
        .pipe(sass())
        .pipe(autoprefixer())
        .pipe(cssnano())
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(cssDest))
});

gulp.task('js', function () {
    return gulp.src(jsSrc+'*.js')
        .pipe(plumber({ errorHandler: onError }))
        .pipe(sourcemaps.init())
        //.pipe(concat(jsFile))
        .pipe(babel())
        .pipe(uglify())
        .pipe(sourcemaps.write("."))
        .pipe(gulp.dest(jsDest));
});

gulp.task('watch', function() {
    gulp.watch(jsSrc+'*.js', ['js'])
    gulp.watch(scssSrc+'*.scss', ['sass']);
});
