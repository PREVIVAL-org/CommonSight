/**
 * Remembers the last result of a function per key and returns it again for the same arguments (reference
 * comparison).
 */

export function memoizeLast<A extends readonly unknown[], R>(fn: (...args: A) => R): (...args: A) => R {
  let lastArgs: A | null = null;
  let lastResult: R;
  return (...args: A): R => {
    if (lastArgs !== null && lastArgs.length === args.length && lastArgs.every((arg, i) => arg === args[i])) {
      return lastResult;
    }
    lastResult = fn(...args);
    lastArgs = args;
    return lastResult;
  };
}

/** Like memoizeLast, but with its own storage per key (e.g. per layer). */
export function memoizeByKey<K, A extends readonly unknown[], R>(
  fn: (key: K, ...args: A) => R,
): (key: K, ...args: A) => R {
  const perKey = new Map<K, (...args: A) => R>();
  return (key: K, ...args: A): R => {
    let memo = perKey.get(key);
    if (memo === undefined) {
      memo = memoizeLast((...inner: A) => fn(key, ...inner));
      perKey.set(key, memo);
    }
    return memo(...args);
  };
}
