// /Composables/useListFilter.js
import { ref, computed } from "vue";

export default function useListFilter(items = [], filterConfig = {}) {
    const list = ref(items);
    const filter = ref({ ...filterConfig });

    const filteredList = computed(() => {
        return list.value.filter((item) => {
            return Object.entries(filter.value).every(([key, filterVal]) => {
                // 🟢 Skip empty filters
                if (
                    filterVal === "" ||
                    filterVal === null ||
                    filterVal === undefined
                )
                    return true;

                // 🟩 Campus Filter (robust)
                if (key === "campus") {
                    const itemCampusVal =
                        item.campus_id ??
                        (item.campus && (item.campus.id ?? item.campus)) ??
                        item.campus ??
                        null;

                    if (itemCampusVal === null || itemCampusVal === undefined)
                        return false;

                    return Number(itemCampusVal) === Number(filterVal);
                }

                // 🟦 Year Filter (robust)
                if (key === "year") {
                    const fy = Number(filterVal);
                    const iv =
                        item.year ??
                        item.year_id ??
                        (item.year && item.year.id) ??
                        null;
                    return iv !== null && Number(iv) === fy;
                }

                // 🟨 Quarter Filter
                if (key === "quarter") {
                    // Define months per quarter
                    const quarters = {
                        Q1: ["january", "february", "march"],
                        Q2: ["april", "may", "june"],
                        Q3: ["july", "august", "september"],
                        Q4: ["october", "november", "december"],
                    };

                    // If "All" or empty quarter → show everything
                    if (filterVal === "" || !filterVal) return true;

                    // If quarter value is valid (e.g., "Q1")
                    const validMonths = quarters[filterVal];
                    if (!validMonths) return true;

                    // ✅ Include only if at least one of the quarter’s months has a value (non-null/non-zero)
                    return validMonths.some(
                        (m) => item[m] !== null && item[m] !== undefined
                    );
                }

                // 🟪 Generic Fallback Filter
                const itemVal =
                    item[key] ??
                    item[`${key}_id`] ??
                    (item[key] && item[key].id) ??
                    null;

                if (itemVal === null || itemVal === undefined) return false;

                if (typeof itemVal === "string") {
                    return itemVal
                        .toLowerCase()
                        .includes(String(filterVal).toLowerCase());
                }

                return String(itemVal) === String(filterVal);
            });
        });
    });

    return { list, filter, filteredList };
}
