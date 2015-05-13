/************************************************************************************
 * DATABASE DOCUMENTATION                                                           *
 *                                                                                  *
 * This readme file contains some useful informations about the database structure  *
 * and eventually about the logic that is present in the persistency layer.         *
 *                                                                                  *
 ************************************************************************************/

 1) Auto-update jobs.last_update
 For every updated row in jobs the column last_update will be filled with CURRENT_TIMESTAMP