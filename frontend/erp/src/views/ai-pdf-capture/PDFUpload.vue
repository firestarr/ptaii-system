<template>
  <!-- Template content here -->
</template>

<script>
export default {
  data() {
    return {
      statusMap: {
        pending: 'Pending',
        processing: 'Processing',
        data_extracted: 'Data Extracted',  // updated from 'extracted'
        so_created: 'Sales Order Created', // added new status
        completed: 'Completed',
        failed: 'Failed'
      },
      // other data properties
    };
  },
  methods: {
    isStatusDataExtracted(status) {
      return status === 'data_extracted';
    },
    isStatusSoCreated(status) {
      return status === 'so_created';
    },
    // other methods with status checks updated accordingly
  }
};
</script>

<style scoped>
/* Styles here */
</style>
