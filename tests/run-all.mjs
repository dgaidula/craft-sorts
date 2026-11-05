// Runs every tests/*-test.php through `ddev craft shell` in your own testbed and
// tallies the `RESULT: <n> passed, <m> failed` line each prints. Never a client site.
import { execFileSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const TESTBED = process.env.TESTBED ?? join(process.env.HOME, 'sw/github-private/craft5-plugin-testbed');
const RESULT = /RESULT:\s*(\d+)\s+passed,\s*(\d+)\s+failed/;

if (!existsSync(TESTBED)) {
  console.error(`No testbed at ${TESTBED}. Set TESTBED= to your own Craft 5 testbed.`);
  process.exit(2);
}

let bad = 0;
for (const name of readdirSync(HERE).filter((f) => f.endsWith('-test.php')).sort()) {
  let out;
  try {
    out = execFileSync('ddev', ['craft', 'shell'], { cwd: TESTBED, input: readFileSync(join(HERE, name), 'utf8'), encoding: 'utf8' });
  } catch (e) {
    out = `${e.stdout ?? ''}${e.stderr ?? ''}`;
  }
  const m = RESULT.exec(out);
  const failed = m ? Number(m[2]) : null;
  if (failed !== 0) bad++;
  console.log(`${failed === null ? 'NO RESULT' : failed ? `${failed} FAILED` : `${m[1]} ok`}  ${name}`);
  if (failed !== 0) {
    out.split('\n').filter((l) => /FAIL|ERROR|Exception/.test(l)).slice(0, 6).forEach((l) => console.log(`    ${l.trim()}`));
  }
}
process.exit(bad ? 1 : 0);
