import { describe, expect, it } from 'vitest';
import { Operation } from '../../core/api/models';
import { operationTotals } from './operation-totals';

function operation(
  amount: number,
  categoryId: number | null,
  transferId: number | null = null,
): Operation {
  return {
    id: Math.abs(amount),
    accountId: 1,
    categoryId,
    amount,
    label: '',
    date: '2026-11-10',
    createdBy: null,
    recurringId: null,
    reconciled: false,
    transfer: transferId === null ? null : { id: transferId, counterpartAccountId: 2, categoryId },
    reimbursement: null,
    editable: true,
  };
}

describe('operationTotals', () => {
  const salary = operation(300000, 10);
  const bread = operation(-420, 20);
  // 200 € put aside: out of the current account (category "Économies"), into the savings account
  const savingsOut = operation(-20000, 30, 7);
  const savingsIn = operation(20000, null, 7);

  it('leaves the transfers out, like the dashboard', () => {
    expect(operationTotals([salary, bread, savingsOut, savingsIn], null)).toEqual({
      income: 300000,
      expenses: 420,
    });
  });

  it('counts a transfer in the category it was given, when filtered on it', () => {
    expect(operationTotals([savingsOut], 30)).toEqual({ income: 0, expenses: 20000 });
    expect(operationTotals([bread], 20)).toEqual({ income: 0, expenses: 420 });
  });
});
