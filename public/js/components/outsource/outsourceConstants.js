// Note 2024-07-08
// I temporary removed RUB and TRY because the Translated API
// does not return the corresponding conversion rates
export const currencies = {
  EUR: {symbol: '€', name: 'Euro (EUR)'},
  USD: {symbol: 'US$', name: 'US dollar (USD)'},
  AUD: {symbol: '$', name: 'Australian dollar (AUD)'},
  CAD: {symbol: '$', name: 'Canadian dollar (CAD)'},
  NZD: {symbol: '$', name: 'New Zealand dollar (NZD)'},
  GBP: {symbol: '£', name: 'Pound sterling (GBP)'},
  BRL: {symbol: 'R$', name: 'Real (BRL)'},
  //RUB: {symbol: 'руб', name: 'Russian ruble (RUB)'},
  SEK: {symbol: 'kr', name: 'Swedish krona (SEK)'},
  CHF: {symbol: 'Fr.', name: 'Swiss franc (CHF)'},
  //TRY: {symbol: 'TL', name: 'Turkish lira (TL)'},
  KRW: {symbol: '￦', name: 'Won (KRW)'},
  JPY: {symbol: '￥', name: 'Yen (JPY)'},
  PLN: {symbol: 'zł', name: 'Złoty (PLN)'},
}

export const timeOptions = [
  {name: '7:00 AM', id: '7'},
  {name: '8:00 AM', id: '8'},
  {name: '9:00 AM', id: '9'},
  {name: '10:00 AM', id: '10'},
  {name: '11:00 AM', id: '11'},
  {name: '12:00 PM', id: '12'},
  {name: '1:00 PM', id: '13'},
  {name: '2:00 PM', id: '14'},
  {name: '3:00 PM', id: '15'},
  {name: '4:00 PM', id: '16'},
  {name: '5:00 PM', id: '17'},
  {name: '6:00 PM', id: '18'},
  {name: '7:00 PM', id: '19'},
  {name: '8:00 PM', id: '20'},
  {name: '9:00 PM', id: '21'},
]

export const formatWithCommas = (value) =>
  String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',')

