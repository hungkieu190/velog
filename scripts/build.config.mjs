/** Explicit frontend entries; imported Sass partials are not entry points. */
export default {
  javascript: { 'src/js/admin.js': 'assets/js/admin.js', 'src/js/frontend.js': 'assets/js/frontend.js' },
  stylesheets: { 'src/css/admin.scss': 'assets/css/admin.css', 'src/css/frontend.scss': 'assets/css/frontend.css' },
  static: {},
  target: ['es2020'],
  format: 'iife',
  external: [],
  manifest: 'assets/.generated.json',
};
