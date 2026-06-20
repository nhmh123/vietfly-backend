# FlightSearchRequest Validation Test Cases

## Endpoint

`POST /api/flights/search`

---

# Valid Cases

| TC | Description | Payload | Expected |
|----|-------------|---------|----------|
| TC001 | One-way trip, only adults | `{trip_type: one-way, origin: SGN, destination: HAN, departure_date: tomorrow, adults:1}` | Pass |
| TC002 | Round-trip with return date | `{trip_type: round-trip, origin: SGN, destination: HAN, departure_date: tomorrow, return_date:+3 days, adults:2}` | Pass |
| TC003 | Adults + children + infants | `{trip_type: round-trip, origin: SGN, destination: DAD, departure_date: tomorrow, return_date:+5 days, adults:2, children:1, infants:1}` | Pass |
| TC004 | Maximum adults allowed | `{adults:9}` | Pass |
| TC005 | Children = 0 | `{children:0}` | Pass |
| TC006 | Infants = 0 | `{infants:0}` | Pass |
| TC007 | Departure date = today | `departure_date=today` | Pass |
| TC008 | Return date = departure date | `return_date == departure_date` | Pass |

---

# trip_type

| TC | Description | Payload | Expected Error |
|----|-------------|---------|---------------|
| TC101 | Missing trip_type | `trip_type=null` | trip_type required |
| TC102 | Invalid value | `trip_type=multi-city` | must be one-way or round-trip |
| TC103 | Number instead of string | `trip_type=1` | must be string |

---

# origin

| TC | Description | Payload | Expected Error |
|----|-------------|---------|---------------|
| TC201 | Missing origin | `origin=null` | origin required |
| TC202 | Length < 3 | `origin=SG` | size:3 |
| TC203 | Length > 3 | `origin=SGNN` | size:3 |
| TC204 | Number instead of string | `origin=123` | string validation |

---

# destination

| TC | Description | Payload | Expected Error |
|----|-------------|---------|---------------|
| TC301 | Missing destination | `destination=null` | destination required |
| TC302 | Length < 3 | `destination=HA` | size:3 |
| TC303 | Length > 3 | `destination=HANOI` | size:3 |
| TC304 | Same as origin | `origin=SGN, destination=SGN` | different:origin |
| TC305 | Number instead of string | `destination=123` | string validation |

---

# departure_date

| TC | Description | Payload | Expected Error |
|----|-------------|---------|---------------|
| TC401 | Missing departure date | `departure_date=null` | required |
| TC402 | Invalid format | `departure_date=abc` | invalid date |
| TC403 | Past date | `departure_date=yesterday` | after_or_equal:today |
| TC404 | Empty string | `departure_date=''` | required |

---

# return_date

| TC | Description | Payload | Expected Error |
|----|-------------|---------|---------------|
| TC501 | Null return date | `return_date=null` | Pass |
| TC502 | Invalid date format | `return_date=abc` | invalid date |
| TC503 | Return date before departure date | `departure_date=2026-06-25, return_date=2026-06-24` | after_or_equal:departure_date |
| TC504 | Empty string | `return_date=''` | invalid date |
| TC505 | Same as departure date | equal dates | Pass |

---

# adults

| TC | Description | Payload | Expected Error |
|----|-------------|---------|---------------|
| TC601 | Missing adults | `adults=null` | required |
| TC602 | adults = 0 | `adults=0` | min:1 |
| TC603 | Negative number | `adults=-1` | min:1 |
| TC604 | adults > 9 | `adults=10` | max:9 |
| TC605 | Decimal number | `adults=1.5` | integer |
| TC606 | String | `adults=abc` | integer |

---

# children

| TC | Description | Payload | Expected Error |
|----|-------------|---------|---------------|
| TC701 | Null children | `children=null` | Pass |
| TC702 | children = 0 | `children=0` | Pass |
| TC703 | Negative value | `children=-1` | min:0 |
| TC704 | Decimal value | `children=1.5` | integer |
| TC705 | String value | `children=abc` | integer |

---

# infants

| TC | Description | Payload | Expected Error |
|----|-------------|---------|---------------|
| TC801 | Null infants | `infants=null` | Pass |
| TC802 | infants = 0 | `infants=0` | Pass |
| TC803 | Negative value | `infants=-1` | min:0 |
| TC804 | Decimal value | `infants=1.5` | integer |
| TC805 | String value | `infants=abc` | integer |

---

# Boundary Value Cases

| TC | Scenario | Payload | Expected |
|----|----------|---------|----------|
| TC901 | Adults minimum | adults=1 | Pass |
| TC902 | Adults maximum | adults=9 | Pass |
| TC903 | Departure date today | departure_date=today | Pass |
| TC904 | Return date = departure date | same date | Pass |
| TC905 | Origin and destination differ by one character | SGN → SGM | Pass |

---

# Combined Invalid Cases

| TC | Scenario | Payload | Expected Errors |
|----|----------|---------|----------------|
| TC1001 | Missing multiple fields | empty body | required errors |
| TC1002 | Same origin and destination + departure in past | origin=SGN, destination=SGN, departure_date=yesterday | destination + departure_date |
| TC1003 | adults=10 and children=-1 | invalid passenger counts | adults, children |
| TC1004 | return date before departure date + invalid trip type | multiple errors | return_date, trip_type |

---

# Business Validation (withValidator)

```php
if ($this->infants > $this->adults) {
    $validator->errors()->add(
        'infants',
        'Infants cannot exceed adults.'
    );
}
```

## Test Cases

| TC | Scenario | Payload | Expected |
|----|----------|---------|---------|
| TC1101 | infants < adults | adults=2, infants=1 | Pass |
| TC1102 | infants = adults | adults=2, infants=2 | Pass |
| TC1103 | infants > adults | adults=1, infants=2 | Error |
| TC1104 | adults=5, infants=6 | Error |
| TC1105 | infants null | infants=null | Pass |

---

# Sample Valid Payload

```json
{
    "trip_type": "round-trip",
    "origin": "SGN",
    "destination": "HAN",
    "departure_date": "2026-06-25",
    "return_date": "2026-06-30",
    "adults": 2,
    "children": 1,
    "infants": 1
}
```