// scripts/set_env_mode.js
import { globSync } from 'glob'
import fs from 'node:fs'

const mode = process.argv[2] // "dev" | "prod"
if (!['dev', 'prod'].includes(mode)) {
  console.error('❌ Please specify a valid mode: dev or prod')
  process.exit(1)
}

console.log(`🚀  Switching to ${mode === 'dev' ? 'development' : 'production'} mode...`)

// evita di scansionare node_modules per velocità
const files = globSync('**/plugin-entry.php', { ignore: ['**/node_modules/**'] })
if (files.length === 0) {
  console.log('⚠️  No plugin-entry.php file found.')
  process.exit(0)
}

for (const file of files) {
  const data = fs.readFileSync(file, 'utf8')

  const from = mode === 'dev'
    ? "define('PluginClassName_PRODUCTION',"
    : "define('PluginClassName_DEVELOPMENT',"
  const to = mode === 'dev'
    ? "define('PluginClassName_DEVELOPMENT',"
    : "define('PluginClassName_PRODUCTION',"

  const result = data.replaceAll(from, to)
  fs.writeFileSync(file, result, 'utf8')

  console.log(`✅  Updated ${file}: ${from} → ${to}`)
}