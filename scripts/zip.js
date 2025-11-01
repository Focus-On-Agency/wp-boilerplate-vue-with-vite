import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

const pluginEntry = 'plugin-entry.php';
const pluginDir = path.basename(process.cwd());

// Legge il contenuto del plugin-entry.php
const content = fs.readFileSync(pluginEntry, 'utf8');

// Regex per estrarre la versione da: * Version: 1.0.6
const match = content.match(/\*\s*Version:\s*([\d.]+)/i);

if (!match) {
	console.error('❌  Version not found in plugin-entry.php');
	process.exit(1);
}

const version = match[1];
const zipName = `${pluginDir}-v${version}.zip`;

console.log(`📦  Creating ZIP: ${zipName}`);

try {
	// Crea lo zip escludendo file di sviluppo
	execSync(
		`zip -r ${zipName} . -x \
			"vite.config.*" \
			"*.config.*" \
			".vite/*" \
			"*.log" \
			"package*.json" \
			"composer*.json" \
			"tsconfig*.json" \
			"eslint*.json" \
			".prettierrc" \
			".stylelintrc*" \
			".trae/*" \
			"node_modules/*" \
			"scripts/*" \
			"aladin.js" \
			"resources/*" \
			"app/Foundation/Console/*" \
			"stubs/*" \
			"README.md" \
			"*.DS_Store" \
			".git*" \
			"!.gitignore" \
			"*.lock"`,
		{ stdio: 'inherit' }
	);
	console.log(`✅  ZIP created: ${zipName}`);
} catch (err) {
	console.error('❌  Failed to create zip:', err);
}