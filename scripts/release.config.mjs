/** Runtime PHP stays in src/; frontend source must never enter the package. */
export default {
  slug: 'velog',
  files: ['velog.php', 'uninstall.php', 'README.md', 'LICENSE'],
  directories: {
    'src/Core': ['.php'], 'src/Admin': ['.php'], 'src/Frontend': ['.php'],
    'src/Api': ['.php'], 'src/Common': ['.php'], 'languages': ['.pot', '.po', '.mo', '.json'],
  },
  required: ['src/Core/Plugin.php', 'src/Core/Loader.php', 'src/Core/Activator.php', 'src/Core/Deactivator.php', 'vendor/autoload.php'],
};
