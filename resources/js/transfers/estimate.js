/**
 * Estimate destination amount based on source amount and exchange rate.
 * - If currencies are the same, returns sourceAmount.
 * - Else returns round(sourceAmount * exchangeRate, 8)
 */
export function estimateDestAmount({ sourceAmount, exchangeRate, sourceCurrency, destCurrency }) {
  const src = Number(sourceAmount || 0);
  const rate = exchangeRate != null ? Number(exchangeRate) : null;
  if (!isFinite(src) || src <= 0) return '0';
  if (sourceCurrency === destCurrency || rate == null) {
    return roundTo(src, 8).toString();
  }
  return roundTo(src * rate, 8).toString();
}

/** Round using decimal-safe approach for display purposes */
function roundTo(num, decimals) {
  const f = Math.pow(10, decimals);
  return Math.round((num + Number.EPSILON) * f) / f;
}

/**
 * Build POST /transfers payload WITHOUT dest_amount (computed server-side).
 * Frontend should still display the estimate to the user.
 */
export function buildTransferPayload(values) {
  const {
    source_account_id,
    destination_account_id,
    source_amount,
    source_currency,
    dest_currency,
    exchange_rate,
    fee,
    fee_category_id,
    source_category_id,
    dest_category_id,
    date,
    description,
    is_private,
  } = values;

  const payload = {
    source_account_id: Number(source_account_id),
    destination_account_id: Number(destination_account_id),
    source_amount: Number(source_amount),
    source_currency: String(source_currency),
    dest_currency: String(dest_currency),
    source_category_id: Number(source_category_id),
    dest_category_id: Number(dest_category_id),
  };

  if (exchange_rate !== undefined && exchange_rate !== null && exchange_rate !== '') {
    payload.exchange_rate = Number(exchange_rate);
  }
  if (fee !== undefined && fee !== null && fee !== '') {
    payload.fee = Number(fee);
  }
  if (fee_category_id) {
    payload.fee_category_id = Number(fee_category_id);
  }
  if (date) payload.date = String(date);
  if (description) payload.description = String(description);
  if (typeof is_private !== 'undefined') payload.is_private = !!is_private;

  return payload;
}

/**
 * Submit transfer via axios. Returns response data.
 */
export async function submitTransfer(payload) {
  const { data } = await window.axios.post('/transfers', payload);
  return data;
}
