const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.webpackConfig({
    stats: {
        children: true
    }
});

mix.js('resources/js/app.js', 'public/js')
   .sass('resources/sass/admin.scss', 'public/css')
   .sass('resources/sass/frontend.scss', 'public/css');

var fs = require('fs');
var path = require('path');
var files = fs.readdirSync('./resources/sass/association');

for (var i=0; i<files.length; i++) {
  if(path.extname(files[i]) == '.scss') {
    mix.sass('resources/sass/association/' + files[i], 'public/css/association');
  }
}

if (mix.inProduction()) {
    mix.version();
}
