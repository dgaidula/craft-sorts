// Runs every tests/*-test.php through `ddev craft shell` in your own testbed and
// tallies the `RESULT: <n> passed, <m> failed` line each prints. Never a client site.
// Each suite puts the testbed back as it found it (tests/support.php); afterwards this
// checks that it did: the testbed's git tree is clean, project config has nothing pending,
// and the queue holds no more jobs than before. Any of those failing fails the run.
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

const run = (cmd, args, input) => execFileSync(cmd, args, { cwd: TESTBED, encoding: 'utf8', ...(input === undefined ? {} : { input }) });
const queueLength = () => Number(/QUEUE=(\d+)/.exec(run('ddev', ['craft', 'shell'], "echo 'QUEUE=' . (new \\craft\\db\\Query())->from(\\craft\\db\\Table::QUEUE)->count() . PHP_EOL;"))?.[1] ?? NaN);
const queueBefore = queueLength();

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
const left = [];
const tree = run('git', ['status', '--short', '-uall']).trim();
if (tree !== '') left.push(`the testbed's git tree is not clean:\n${tree}`);
const diff = run('ddev', ['craft', 'project-config/diff']);
if (!diff.includes('No pending project config YAML changes.')) left.push(`project config has pending changes:\n${diff.trim().split('\n').slice(0, 12).join('\n')}`);
const queueAfter = queueLength();
if (!(queueAfter <= queueBefore)) left.push(`the queue grew from ${queueBefore} to ${queueAfter} jobs`);
if (left.length) {
  bad++;
  console.log('FAILED  testbed isolation');
  left.forEach((l) => console.log(l.split('\n').map((x) => `    ${x}`).join('\n')));
} else {
  console.log(`ok  testbed isolation (clean tree, no pending project config, queue ${queueBefore} → ${queueAfter})`);
}
process.exit(bad ? 1 : 0);
