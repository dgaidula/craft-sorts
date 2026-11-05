// Runs every tests/*-test.php through `ddev craft shell` in your own testbed and
// tallies the `RESULT: <n> passed, <m> failed` line each prints. Never a client site.
// Each suite puts the testbed back as it found it (tests/support.php); afterwards this
// checks that it did: the testbed's git tree is clean, project config has nothing pending,
// and the queue holds no more jobs than before. Any of those failing fails the run.
//
// It will not start on a testbed that is already dirty (a changed tree or pending project
// config), since it would take that state as the one to put back. A run killed by a PHP fatal
// leaves project config that way; `node tests/run-all.mjs --reset` puts back the committed
// config/project YAML and applies it, and nothing else. No run discards changes by itself.
import { execFileSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const TESTBED = process.env.TESTBED ?? join(process.env.HOME, 'sw/github-private/craft5-plugin-testbed');
const RESULT = /RESULT:\s*(\d+)\s+passed,\s*(\d+)\s+failed/;
const RESET = 'node tests/run-all.mjs --reset';
const args = process.argv.slice(2);
if (args.some((a) => a !== '--reset')) {
  console.error(`Unknown argument: ${args.find((a) => a !== '--reset')}. The only option is --reset.`);
  process.exit(2);
}

if (!existsSync(TESTBED)) {
  console.error(`No testbed at ${TESTBED}. Set TESTBED= to your own Craft 5 testbed.`);
  process.exit(2);
}

const run = (cmd, args, input) => execFileSync(cmd, args, { cwd: TESTBED, encoding: 'utf8', ...(input === undefined ? {} : { input }) });
// The queue length; also forgets the control panel session the suites share (tests/support.php).
const queueLength = () => Number(/QUEUE=(\d+)/.exec(run('ddev', ['craft', 'shell'], "@unlink(\\Craft::$app->getPath()->getRuntimePath() . '/sorts-tests-cp-cookies.json'); echo 'QUEUE=' . (new \\craft\\db\\Query())->from(\\craft\\db\\Table::QUEUE)->count() . PHP_EOL;"))?.[1] ?? NaN);
const pending = () => {
  const diff = run('ddev', ['craft', 'project-config/diff']);
  return diff.includes('No pending project config YAML changes.') ? '' : diff.trim().split('\n').slice(0, 12).join('\n');
};
const indent = (text) => text.split('\n').map((x) => `    ${x}`).join('\n');

if (args.includes('--reset')) {
  const changed = run('git', ['status', '--short', '-uall', '--', 'config/project']).trimEnd();
  const untracked = changed.split('\n').filter((l) => l.startsWith('??'));
  if (changed !== '') console.log(`Putting back the committed config/project YAML; this discards:\n${indent(changed)}`);
  run('git', ['checkout', '--', 'config/project']);
  console.log(indent(run('ddev', ['craft', 'project-config/apply']).trim()));
  const still = pending();
  if (untracked.length) console.log(`Left in place, not committed and not this tool's to delete:\n${indent(untracked.join('\n'))}`);
  if (still) console.log(`Project config still has pending changes:\n${indent(still)}`);
  console.log(untracked.length || still ? 'FAILED  reset' : 'ok  reset (config/project is as committed and applied)');
  process.exit(untracked.length || still ? 1 : 0);
}

const start = [];
const dirty = run('git', ['status', '--short', '-uall']).trimEnd();
if (dirty !== '') start.push(`the testbed's git tree is not clean:\n${dirty}`);
const startPending = pending();
if (startPending) start.push(`project config has pending changes:\n${startPending}`);
if (start.length) {
  console.log('FAILED  testbed start: not taking a dirty testbed as the state to put back');
  start.forEach((l) => console.log(indent(l)));
  const config = startPending !== '' || /\sconfig\/project\//.test(dirty);
  console.log(indent(config
    ? `A run killed by a PHP fatal leaves project config like this. If none of it is yours, put the committed YAML back and apply it:\n  ${RESET}`
    : 'Commit or stash these first; run-all never discards changes.'));
  process.exit(2);
}
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
const endPending = pending();
if (endPending) left.push(`project config has pending changes:\n${endPending}`);
const queueAfter = queueLength();
if (!(queueAfter <= queueBefore)) left.push(`the queue grew from ${queueBefore} to ${queueAfter} jobs`);
if (left.length) {
  bad++;
  console.log('FAILED  testbed isolation');
  left.forEach((l) => console.log(indent(l)));
  if (endPending || /\sconfig\/project\//.test(tree)) console.log(indent(`If none of the project config changes are yours: ${RESET}`));
} else {
  console.log(`ok  testbed isolation (clean tree, no pending project config, queue ${queueBefore} → ${queueAfter})`);
}
process.exit(bad ? 1 : 0);
