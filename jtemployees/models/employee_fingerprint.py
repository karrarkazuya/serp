from odoo import models, fields, exceptions
from pytz import timezone

# to be removed
class JTEmployeesImages(models.Model):
    _name = 'jtemployees.fingerprint'
    _description = 'Log for the fingerprints coming from the api'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    user_id               = fields.Integer(string='User ID', tracking=True)
    record_date           = fields.Date(string='Record date', required=True, tracking=True)
    check_in              = fields.Datetime(string='Record date', required=True, tracking=True)
    check_out             = fields.Datetime(string='Record date', required=True, tracking=True)
    data_reference        = fields.Text(string='Data Reference')
    
    
    _sql_constraints = [
        ('data_reference_unique', 'unique(data_reference)', 'The data_reference must be unique.')
    ]
    
    def create(self, vals_list):
        result = super().create(vals_list)
        employee = self.env['hr.employee'].with_user(1).sudo().search([('jt_fingerprint_id', '=', result.user_id)])
        
        # Define your specific timezone
        local_tz = timezone('Asia/Baghdad')
        
        # Convert naive datetimes and localize them
        check_in_naive = fields.Datetime.to_datetime(vals_list['check_in'])
        check_out_naive = fields.Datetime.to_datetime(vals_list['check_out'])
        
        check_in_utc = local_tz.localize(check_in_naive).astimezone(timezone('UTC')).replace(tzinfo=None)
        check_out_utc = local_tz.localize(check_out_naive).astimezone(timezone('UTC')).replace(tzinfo=None)
    
        payload = {
            "check_in": check_in_utc,
            "check_out": check_out_utc,
            "employee_id": employee.id
        }
        self.env['hr.attendance'].with_user(1).sudo().create(payload)
        
        return result
