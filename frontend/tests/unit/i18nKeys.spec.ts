import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { describe, expect, it } from 'vitest'
// Read from disk: importing the JSON would go through the i18n build plugin,
// which compiles messages into functions.
const messages = JSON.parse(readFileSync(join(process.cwd(), 'i18n/locales/en.json'), 'utf8')) as Record<string, unknown>

/*
 * vue-i18n accepts any string as a key, so a typo compiles and silently shows
 * the raw key. This test reads every t('…') / $t('…') / keypath="…" in the
 * source and checks the key exists in the locale file. Template-literal keys
 * (t(`status.${status}`)) are checked by their static prefix.
 */
const ROOT = process.cwd()
const SOURCE_DIRS = ['components', 'composables', 'pages', 'stores', 'layouts', 'plugins', 'middleware']
const SOURCE_FILES = ['error.vue', 'app.vue']

function sourceFiles(): string[] {
  const files: string[] = []
  const walk = (dir: string) => {
    let entries: string[]
    try {
      entries = readdirSync(dir)
    } catch {
      return
    }
    for (const entry of entries) {
      const path = join(dir, entry)
      if (statSync(path).isDirectory()) walk(path)
      else if (/\.(vue|ts)$/.test(entry)) files.push(path)
    }
  }
  SOURCE_DIRS.forEach(dir => walk(join(ROOT, dir)))
  SOURCE_FILES.map(file => join(ROOT, file)).forEach(file => {
    try {
      statSync(file)
      files.push(file)
    } catch {
      // optional file
    }
  })
  return files
}

function lookup(key: string): unknown {
  return key.split('.').reduce<unknown>((node, part) => (node && typeof node === 'object' ? (node as Record<string, unknown>)[part] : undefined), messages)
}

const STATIC_KEY = /(?:\$t|\bt)\(\s*'([^']+)'|keypath="([^"]+)"/g
const TEMPLATE_KEY = /(?:\$t|\bt)\(\s*`([^`$]+)\$\{/g

describe('i18n keys', () => {
  const files = sourceFiles()

  it('scans the source', () => {
    expect(files.length).toBeGreaterThan(10)
  })

  it('every static key used in the source exists in en.json as a message', () => {
    const missing: string[] = []
    for (const file of files) {
      for (const match of readFileSync(file, 'utf8').matchAll(STATIC_KEY)) {
        const key = (match[1] ?? match[2])!
        if (typeof lookup(key) !== 'string') missing.push(`${relative(ROOT, file)}: ${key}`)
      }
    }
    expect(missing).toEqual([])
  })

  it('every dynamic key prefix used in the source exists in en.json as a group', () => {
    const missing: string[] = []
    for (const file of files) {
      for (const match of readFileSync(file, 'utf8').matchAll(TEMPLATE_KEY)) {
        const prefix = match[1]!.replace(/\.$/, '')
        const node = lookup(prefix)
        if (!node || typeof node !== 'object') missing.push(`${relative(ROOT, file)}: ${prefix}.*`)
      }
    }
    expect(missing).toEqual([])
  })

  it('has groups for every status, tier, sort and error reason the app can show', () => {
    expect(Object.keys(lookup('status') as object).sort()).toEqual(['pending', 'rejected', 'shortlisted'])
    expect(Object.keys(lookup('tier') as object).sort()).toEqual(['review', 'strong', 'top'])
    expect(Object.keys(lookup('review.sort') as object).sort()).toEqual(['-aiScore', '-appliedAt', '-name', 'aiScore', 'appliedAt', 'name'])
    expect(Object.keys(lookup('errorPage.reason') as object).sort()).toEqual(['invalid-id', 'not-found', 'other', 'unauthorized', 'unavailable'])
  })
})
