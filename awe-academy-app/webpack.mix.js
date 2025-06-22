const mix = require('laravel-mix');
const path = require('path');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your application. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

// Set public path
mix.setPublicPath('public');

// JavaScript
mix.js('resources/js/app.js', 'public/js')
   .js('resources/js/dashboard.js', 'public/js')
   .js('resources/js/students.js', 'public/js')
   .js('resources/js/fees.js', 'public/js')
   .js('resources/js/payments.js', 'public/js')
   .js('resources/js/supplies.js', 'public/js')
   .js('resources/js/reports.js', 'public/js');

// Styles
mix.sass('resources/scss/app.scss', 'public/css')
   .sass('resources/scss/dashboard.scss', 'public/css')
   .sass('resources/scss/auth.scss', 'public/css')
   .sass('resources/scss/print.scss', 'public/css');

// Vendor extraction
mix.extract([
    'jquery',
    'bootstrap',
    '@popperjs/core',
    'chart.js',
    'datatables.net-bs5',
    'sweetalert2',
    'moment',
    'flatpickr'
]);

// Copy assets
mix.copy('node_modules/@fortawesome/fontawesome-free/webfonts', 'public/webfonts')
   .copy('resources/images', 'public/images');

// Source maps
if (!mix.inProduction()) {
    mix.sourceMaps();
}

// Versioning
if (mix.inProduction()) {
    mix.version();
}

// BrowserSync for development
if (!mix.inProduction()) {
    mix.browserSync({
        proxy: 'localhost:8000',
        files: [
            'public/**/*',
            'resources/**/*',
            'modules/**/*.php',
            'includes/**/*.php',
            '*.php'
        ]
    });
}

// Build configuration
mix.options({
    processCssUrls: false,
    terser: {
        extractComments: false,
        terserOptions: {
            compress: {
                drop_console: mix.inProduction()
            }
        }
    },
    postCss: [
        require('autoprefixer')({
            browsers: ['> 1%', 'last 2 versions', 'not dead']
        })
    ]
});

// Webpack configuration
mix.webpackConfig({
    resolve: {
        alias: {
            '@': path.resolve('resources/js'),
            '~': path.resolve('resources/scss')
        }
    },
    output: {
        chunkFilename: 'js/chunks/[name].[chunkhash].js'
    },
    optimization: {
        splitChunks: {
            chunks: 'all',
            minSize: 20000,
            maxSize: 250000,
            cacheGroups: {
                defaultVendors: {
                    test: /[\\/]node_modules[\\/]/,
                    priority: -10,
                    reuseExistingChunk: true
                },
                default: {
                    minChunks: 2,
                    priority: -20,
                    reuseExistingChunk: true
                }
            }
        }
    }
});

// Handle production-specific optimizations
if (mix.inProduction()) {
    mix.options({
        clearConsole: true,
        cssNano: {
            discardComments: {removeAll: true}
        }
    });
}

// Disable success notifications during development
mix.disableSuccessNotifications();

// Handle errors
mix.catch(error => {
    console.error('Mix Build Error:', error);
    process.exit(1);
});
