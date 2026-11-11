import { Operation } from '../../core/api/models';

/**
 * Income and expenses of a list of operations, as the dashboard counts them: money moved by a
 * transfer is neither. Except, on a list filtered on a category, the transfers given that category
 * (savings…): they count as in its budget.
 */
export function operationTotals(
  operations: Operation[],
  categoryId: number | null,
): { income: number; expenses: number } {
  const counted = operations.filter(
    (o) => !o.transfer || (categoryId !== null && o.categoryId === categoryId),
  );
  return {
    income: counted.filter((o) => o.amount > 0).reduce((sum, o) => sum + o.amount, 0),
    expenses: counted.filter((o) => o.amount < 0).reduce((sum, o) => sum - o.amount, 0),
  };
}
